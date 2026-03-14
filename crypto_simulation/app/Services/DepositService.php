<?php

namespace App\Services;

use App\Models\User;
use App\Models\Deposit;
use App\Models\Wallet;
use App\Services\Contracts\WalletManagerInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DepositService
{
    private WalletManagerInterface $walletManager;

    public function __construct(WalletManagerInterface $walletManager)
    {
        $this->walletManager = $walletManager;
    }

    /**
     * Generate a deposit address for crypto deposits
     */
    public function generateDepositAddress(User $user, string $currency): array
    {
        try {
            // For simulation, generate a mock address
            $address = $this->generateMockAddress($currency);
            
            Log::info('Deposit address generated', [
                'user_id' => $user->id,
                'currency' => $currency,
                'address' => $address
            ]);

            return [
                'success' => true,
                'address' => $address,
                'currency' => $currency,
                'qr_code' => $this->generateQRCode($address),
                'instructions' => $this->getDepositInstructions($currency)
            ];

        } catch (\Exception $e) {
            Log::error('Failed to generate deposit address', [
                'user_id' => $user->id,
                'currency' => $currency,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to generate deposit address'
            ];
        }
    }

    /**
     * Create a new deposit record
     */
    public function createDeposit(array $data): array
    {
        try {
            DB::beginTransaction();

            $deposit = Deposit::create([
                'user_id' => $data['user_id'],
                'currency' => $data['currency'],
                'type' => $data['type'] ?? 'crypto',
                'amount' => $data['amount'],
                'fee' => $data['fee'] ?? 0,
                'net_amount' => $data['amount'] - ($data['fee'] ?? 0),
                'wallet_address' => $data['wallet_address'] ?? null,
                'txid' => $data['txid'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'payment_reference' => $data['payment_reference'] ?? null,
                'payment_details' => $data['payment_details'] ?? null,
                'required_confirmations' => $this->getRequiredConfirmations($data['currency']),
                'status' => 'pending',
                'metadata' => $data['metadata'] ?? null,
            ]);

            DB::commit();

            Log::info('Deposit created', [
                'deposit_id' => $deposit->id,
                'user_id' => $deposit->user_id,
                'currency' => $deposit->currency,
                'amount' => $deposit->amount
            ]);

            return [
                'success' => true,
                'deposit' => $deposit,
                'message' => 'Deposit created successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create deposit', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create deposit'
            ];
        }
    }

    /**
     * Process crypto deposit confirmation
     */
    public function processDepositConfirmation(string $txid, int $confirmations): array
    {
        try {
            $deposit = Deposit::where('txid', $txid)->first();
            
            if (!$deposit) {
                return [
                    'success' => false,
                    'message' => 'Deposit not found'
                ];
            }

            // Mark as confirming if it's still pending
            if ($deposit->status === 'pending' && $confirmations > 0) {
                $deposit->markAsConfirming();
            }

            $deposit->updateConfirmations($confirmations);

            // If deposit is completed, credit user wallet
            if ($deposit->status === 'completed') {
                $this->creditUserWallet($deposit);
            }

            return [
                'success' => true,
                'deposit' => $deposit,
                'message' => 'Deposit confirmation updated'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to process deposit confirmation', [
                'txid' => $txid,
                'confirmations' => $confirmations,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to process confirmation'
            ];
        }
    }

    /**
     * Process fiat deposit
     */
    public function processFiatDeposit(array $data): array
    {
        try {
            DB::beginTransaction();

            $deposit = $this->createDeposit($data);
            
            if (!$deposit['success']) {
                DB::rollBack();
                return $deposit;
            }

            // For fiat deposits, mark as completed immediately (in real system, this would be after payment verification)
            $depositModel = $deposit['deposit'];
            $depositModel->markAsCompleted();
            
            // Credit user wallet
            $this->creditUserWallet($depositModel);

            DB::commit();

            return [
                'success' => true,
                'deposit' => $depositModel,
                'message' => 'Fiat deposit processed successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to process fiat deposit', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to process fiat deposit'
            ];
        }
    }

    /**
     * Get user deposits
     */
    public function getUserDeposits(User $user, array $filters = []): array
    {
        try {
            $query = Deposit::where('user_id', $user->id)
                           ->with(['processedBy'])
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

            $deposits = $query->paginate($filters['per_page'] ?? 20);

            return [
                'success' => true,
                'deposits' => $deposits,
                'summary' => $this->getDepositSummary($user)
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get user deposits', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve deposits'
            ];
        }
    }

    /**
     * Credit user wallet after successful deposit
     */
    private function creditUserWallet(Deposit $deposit): void
    {
        $this->walletManager->creditWallet(
            $deposit->user,
            $deposit->currency,
            $deposit->net_amount,
            'deposit',
            "Deposit #{$deposit->id}"
        );

        Log::info('Wallet credited for deposit', [
            'deposit_id' => $deposit->id,
            'user_id' => $deposit->user_id,
            'currency' => $deposit->currency,
            'amount' => $deposit->net_amount
        ]);
    }

    /**
     * Generate mock address for simulation
     */
    private function generateMockAddress(string $currency): string
    {
        $prefixes = [
            'BTC' => ['1', '3', 'bc1'],
            'ETH' => ['0x'],
            'LTC' => ['L', 'M', 'ltc1'],
            'BCH' => ['1', '3', 'q'],
            'XRP' => ['r'],
        ];

        $prefix = $prefixes[$currency][0] ?? '1';
        $length = $currency === 'ETH' ? 40 : 32;
        
        return $prefix . bin2hex(random_bytes($length / 2));
    }

    /**
     * Generate QR code URL for address
     */
    private function generateQRCode(string $address): string
    {
        return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($address);
    }

    /**
     * Get deposit instructions for currency
     */
    private function getDepositInstructions(string $currency): array
    {
        $instructions = [
            'BTC' => [
                'Send only Bitcoin (BTC) to this address',
                'Minimum deposit: 0.001 BTC',
                'Confirmations required: 3',
                'Network: Bitcoin Mainnet'
            ],
            'ETH' => [
                'Send only Ethereum (ETH) to this address',
                'Minimum deposit: 0.01 ETH',
                'Confirmations required: 12',
                'Network: Ethereum Mainnet'
            ],
            'LTC' => [
                'Send only Litecoin (LTC) to this address',
                'Minimum deposit: 0.01 LTC',
                'Confirmations required: 6',
                'Network: Litecoin Mainnet'
            ]
        ];

        return $instructions[$currency] ?? [
            "Send only {$currency} to this address",
            'Check minimum deposit requirements',
            'Wait for network confirmations'
        ];
    }

    /**
     * Get required confirmations for currency
     */
    private function getRequiredConfirmations(string $currency): int
    {
        $confirmations = [
            'BTC' => 3,
            'ETH' => 12,
            'LTC' => 6,
            'BCH' => 6,
            'XRP' => 1,
        ];

        return $confirmations[$currency] ?? 3;
    }

    /**
     * Get deposit summary for user
     */
    private function getDepositSummary(User $user): array
    {
        $deposits = Deposit::where('user_id', $user->id);

        return [
            'total_deposits' => $deposits->count(),
            'completed_deposits' => $deposits->where('status', 'completed')->count(),
            'pending_deposits' => $deposits->where('status', 'pending')->count(),
            'total_amount' => $deposits->where('status', 'completed')->sum('net_amount'),
        ];
    }
}