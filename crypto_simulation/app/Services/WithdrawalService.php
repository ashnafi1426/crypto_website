<?php

namespace App\Services;

use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Contracts\WalletManagerInterface;
use App\Services\OtpVerificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WithdrawalService
{
    private WalletManagerInterface $walletManager;
    private OtpVerificationService $otpService;

    public function __construct(
        WalletManagerInterface $walletManager,
        OtpVerificationService $otpService
    ) {
        $this->walletManager = $walletManager;
        $this->otpService = $otpService;
    }

    /**
     * Create a new withdrawal request
     */
    public function createWithdrawal(User $user, array $data): array
    {
        try {
            DB::beginTransaction();

            // Validate user has sufficient balance
            $wallet = $this->walletManager->getWallet($user, $data['currency']);
            if (!$wallet || $wallet->balance < $data['amount']) {
                return [
                    'success' => false,
                    'message' => 'Insufficient balance'
                ];
            }

            // Calculate fees
            $fee = $this->calculateWithdrawalFee($data['currency'], $data['amount']);
            $netAmount = $data['amount'] - $fee;

            if ($netAmount <= 0) {
                return [
                    'success' => false,
                    'message' => 'Amount too small after fees'
                ];
            }

            // Create withdrawal record
            $withdrawal = Withdrawal::create([
                'user_id' => $user->id,
                'currency' => $data['currency'],
                'type' => $data['type'] ?? 'crypto',
                'amount' => $data['amount'],
                'fee' => $fee,
                'net_amount' => $netAmount,
                'to_address' => $data['to_address'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'payment_details' => $data['payment_details'] ?? null,
                'status' => 'pending',
                'metadata' => $data['metadata'] ?? null,
            ]);

            // Generate verification code
            $verificationCode = $withdrawal->generateVerificationCode();

            // Send verification email
            $this->sendWithdrawalVerificationEmail($user, $withdrawal, $verificationCode);

            // Hold the funds in user's wallet
            $this->walletManager->holdFunds(
                $user,
                $data['currency'],
                $data['amount'],
                "Withdrawal #{$withdrawal->id}"
            );

            DB::commit();

            Log::info('Withdrawal request created', [
                'withdrawal_id' => $withdrawal->id,
                'user_id' => $user->id,
                'currency' => $data['currency'],
                'amount' => $data['amount']
            ]);

            return [
                'success' => true,
                'withdrawal' => $withdrawal,
                'message' => 'Withdrawal request created. Please check your email for verification.'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create withdrawal', [
                'user_id' => $user->id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create withdrawal request'
            ];
        }
    }
    /**
     * Verify withdrawal with email code
     */
    public function verifyWithdrawal(User $user, int $withdrawalId, string $code): array
    {
        try {
            $withdrawal = Withdrawal::where('id', $withdrawalId)
                                   ->where('user_id', $user->id)
                                   ->first();

            if (!$withdrawal) {
                return [
                    'success' => false,
                    'message' => 'Withdrawal not found'
                ];
            }

            if (!$withdrawal->verifyCode($code)) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired verification code'
                ];
            }

            // Check if withdrawal needs 2FA verification
            if ($user->two_factor_enabled && !$withdrawal->two_factor_verified) {
                return [
                    'success' => true,
                    'requires_2fa' => true,
                    'message' => 'Email verified. Please complete 2FA verification.'
                ];
            }

            // Mark as verified if all verifications are complete
            if ($withdrawal->email_verified && ($withdrawal->two_factor_verified || !$user->two_factor_enabled)) {
                $withdrawal->markAsVerified();
            }

            return [
                'success' => true,
                'withdrawal' => $withdrawal,
                'message' => 'Withdrawal verified successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to verify withdrawal', [
                'user_id' => $user->id,
                'withdrawal_id' => $withdrawalId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to verify withdrawal'
            ];
        }
    }

    /**
     * Verify 2FA for withdrawal
     */
    public function verify2FA(User $user, int $withdrawalId, string $code): array
    {
        try {
            $withdrawal = Withdrawal::where('id', $withdrawalId)
                                   ->where('user_id', $user->id)
                                   ->first();

            if (!$withdrawal) {
                return [
                    'success' => false,
                    'message' => 'Withdrawal not found'
                ];
            }

            // Verify 2FA code (this would integrate with your 2FA service)
            // For now, we'll simulate it
            if ($this->verify2FACode($user, $code)) {
                $withdrawal->update(['two_factor_verified' => true]);

                // Mark as verified if all verifications are complete
                if ($withdrawal->email_verified && $withdrawal->two_factor_verified) {
                    $withdrawal->markAsVerified();
                }

                return [
                    'success' => true,
                    'withdrawal' => $withdrawal,
                    'message' => '2FA verified successfully'
                ];
            }

            return [
                'success' => false,
                'message' => 'Invalid 2FA code'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to verify 2FA for withdrawal', [
                'user_id' => $user->id,
                'withdrawal_id' => $withdrawalId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to verify 2FA'
            ];
        }
    }

    /**
     * Admin approve withdrawal
     */
    public function approveWithdrawal(User $admin, int $withdrawalId): array
    {
        try {
            $withdrawal = Withdrawal::find($withdrawalId);

            if (!$withdrawal) {
                return [
                    'success' => false,
                    'message' => 'Withdrawal not found'
                ];
            }

            if (!$withdrawal->can_be_approved) {
                return [
                    'success' => false,
                    'message' => 'Withdrawal cannot be approved in current state'
                ];
            }

            $withdrawal->markAsApproved($admin);

            // Process the withdrawal
            $this->processWithdrawal($withdrawal);

            Log::info('Withdrawal approved', [
                'withdrawal_id' => $withdrawal->id,
                'admin_id' => $admin->id
            ]);

            return [
                'success' => true,
                'withdrawal' => $withdrawal,
                'message' => 'Withdrawal approved and processing'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to approve withdrawal', [
                'withdrawal_id' => $withdrawalId,
                'admin_id' => $admin->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to approve withdrawal'
            ];
        }
    }

    /**
     * Admin reject withdrawal
     */
    public function rejectWithdrawal(User $admin, int $withdrawalId, string $reason): array
    {
        try {
            DB::beginTransaction();

            $withdrawal = Withdrawal::find($withdrawalId);

            if (!$withdrawal) {
                return [
                    'success' => false,
                    'message' => 'Withdrawal not found'
                ];
            }

            $withdrawal->markAsRejected($reason, $admin);

            // Release held funds
            $this->walletManager->releaseFunds(
                $withdrawal->user,
                $withdrawal->currency,
                $withdrawal->amount,
                "Withdrawal #{$withdrawal->id} rejected"
            );

            DB::commit();

            Log::info('Withdrawal rejected', [
                'withdrawal_id' => $withdrawal->id,
                'admin_id' => $admin->id,
                'reason' => $reason
            ]);

            return [
                'success' => true,
                'withdrawal' => $withdrawal,
                'message' => 'Withdrawal rejected'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to reject withdrawal', [
                'withdrawal_id' => $withdrawalId,
                'admin_id' => $admin->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to reject withdrawal'
            ];
        }
    }

    /**
     * Get user withdrawals
     */
    public function getUserWithdrawals(User $user, array $filters = []): array
    {
        try {
            $query = Withdrawal::where('user_id', $user->id)
                              ->with(['approvedBy', 'processedBy'])
                              ->orderBy('created_at', 'desc');

            // Apply filters
            if (isset($filters['currency'])) {
                $query->where('currency', $filters['currency']);
            }

            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (isset($filters['type'])) {
                $query->where('type', $filters['type']);
            }

            $withdrawals = $query->paginate($filters['per_page'] ?? 20);

            return [
                'success' => true,
                'withdrawals' => $withdrawals,
                'summary' => $this->getWithdrawalSummary($user)
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get user withdrawals', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve withdrawals'
            ];
        }
    }

    /**
     * Process withdrawal (send to blockchain/bank)
     */
    private function processWithdrawal(Withdrawal $withdrawal): void
    {
        try {
            $withdrawal->markAsProcessing();

            // Simulate processing delay
            if ($withdrawal->type === 'crypto') {
                // Simulate blockchain transaction
                $txid = $this->simulateBlockchainTransaction($withdrawal);
                $withdrawal->markAsCompleted($txid);
            } else {
                // Simulate fiat processing
                $withdrawal->markAsCompleted();
            }

            // Deduct from user wallet
            $this->walletManager->debitWallet(
                $withdrawal->user,
                $withdrawal->currency,
                $withdrawal->amount,
                'withdrawal',
                "Withdrawal #{$withdrawal->id}"
            );

        } catch (\Exception $e) {
            $withdrawal->markAsFailed($e->getMessage());
            
            Log::error('Failed to process withdrawal', [
                'withdrawal_id' => $withdrawal->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Calculate withdrawal fee
     */
    private function calculateWithdrawalFee(string $currency, float $amount): float
    {
        $fees = [
            'BTC' => 0.0005,
            'ETH' => 0.005,
            'LTC' => 0.001,
            'USD' => 5.00,
            'EUR' => 5.00,
        ];

        return $fees[$currency] ?? 0.001;
    }

    /**
     * Simulate blockchain transaction
     */
    private function simulateBlockchainTransaction(Withdrawal $withdrawal): string
    {
        // Generate mock transaction ID
        return bin2hex(random_bytes(32));
    }

    /**
     * Verify 2FA code (mock implementation)
     */
    private function verify2FACode(User $user, string $code): bool
    {
        // This would integrate with your actual 2FA service
        // For simulation, accept any 6-digit code
        return preg_match('/^\d{6}$/', $code);
    }

    /**
     * Send withdrawal verification email
     */
    private function sendWithdrawalVerificationEmail(User $user, Withdrawal $withdrawal, string $code): void
    {
        try {
            // This would send an actual email
            // For now, just log it
            Log::info('Withdrawal verification email sent', [
                'user_id' => $user->id,
                'withdrawal_id' => $withdrawal->id,
                'verification_code' => $code
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send withdrawal verification email', [
                'user_id' => $user->id,
                'withdrawal_id' => $withdrawal->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get withdrawal summary for user
     */
    private function getWithdrawalSummary(User $user): array
    {
        $withdrawals = Withdrawal::where('user_id', $user->id);

        return [
            'total_withdrawals' => $withdrawals->count(),
            'completed_withdrawals' => $withdrawals->where('status', 'completed')->count(),
            'pending_withdrawals' => $withdrawals->whereIn('status', ['pending', 'verified', 'approved', 'processing'])->count(),
            'total_amount' => $withdrawals->where('status', 'completed')->sum('net_amount'),
        ];
    }
}