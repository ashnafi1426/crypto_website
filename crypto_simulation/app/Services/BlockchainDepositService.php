<?php

namespace App\Services;

use App\Models\Deposit;
use App\Models\DepositAddress;
use App\Models\Wallet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\DepositConfirmedMail;

class BlockchainDepositService
{
    private EthereumService $ethereumService;

    public function __construct(EthereumService $ethereumService)
    {
        $this->ethereumService = $ethereumService;
    }

    /**
     * Process an Ethereum deposit transaction
     */
    public function processEthereumDeposit(DepositAddress $depositAddress, array $transaction): array
    {
        try {
            DB::beginTransaction();

            // Check if deposit already exists
            $existingDeposit = Deposit::where('txid', $transaction['hash'])->first();
            if ($existingDeposit) {
                DB::rollBack();
                return [
                    'success' => true,
                    'message' => 'Deposit already processed',
                    'deposit' => $existingDeposit,
                    'confirmed' => $existingDeposit->status === 'completed'
                ];
            }

            // Validate transaction amount (must be > 0)
            $amount = (float) $transaction['value'];
            if ($amount <= 0) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Invalid transaction amount'
                ];
            }

            // Get confirmations
            $confirmations = $this->ethereumService->getConfirmations($transaction['blockNumber']);
            $isConfirmed = $this->ethereumService->hasEnoughConfirmations($transaction['blockNumber']);

            // Create deposit record
            $deposit = Deposit::create([
                'user_id' => $depositAddress->user_id,
                'currency' => 'ETH',
                'type' => 'crypto',
                'amount' => $amount,
                'wallet_address' => $depositAddress->address,
                'txid' => $transaction['hash'],
                'network' => 'Ethereum',
                'confirmations' => $confirmations,
                'status' => $isConfirmed ? 'completed' : 'pending',
                'from_address' => $transaction['from'],
                'block_number' => $transaction['blockNumber'],
                'transaction_fee' => 0, // We don't track fees for incoming deposits
                'processed_at' => $isConfirmed ? now() : null
            ]);

            // If confirmed, credit user wallet
            if ($isConfirmed) {
                $this->creditUserWallet($depositAddress->user_id, 'ETH', $amount);
                $this->sendDepositNotification($deposit);
            }

            // Update deposit address last used
            $depositAddress->markAsUsed();

            DB::commit();

            Log::info('Ethereum deposit processed', [
                'deposit_id' => $deposit->id,
                'user_id' => $depositAddress->user_id,
                'amount' => $amount,
                'tx_hash' => $transaction['hash'],
                'confirmations' => $confirmations,
                'confirmed' => $isConfirmed
            ]);

            return [
                'success' => true,
                'message' => 'Deposit processed successfully',
                'deposit' => $deposit,
                'confirmed' => $isConfirmed
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to process Ethereum deposit', [
                'address' => $depositAddress->address,
                'tx_hash' => $transaction['hash'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to process deposit: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update pending deposits and check for confirmations
     */
    public function updatePendingDeposits(): int
    {
        $pendingDeposits = Deposit::where('status', 'pending')
            ->where('currency', 'ETH')
            ->whereNotNull('block_number')
            ->get();

        $confirmedCount = 0;

        foreach ($pendingDeposits as $deposit) {
            try {
                $confirmations = $this->ethereumService->getConfirmations($deposit->block_number);
                
                // Update confirmations count
                $deposit->update(['confirmations' => $confirmations]);

                // Check if now confirmed
                if ($this->ethereumService->hasEnoughConfirmations($deposit->block_number)) {
                    DB::beginTransaction();

                    // Update deposit status
                    $deposit->update([
                        'status' => 'completed',
                        'processed_at' => now()
                    ]);

                    // Credit user wallet
                    $this->creditUserWallet($deposit->user_id, $deposit->currency, $deposit->amount);

                    // Send notification
                    $this->sendDepositNotification($deposit);

                    DB::commit();
                    $confirmedCount++;

                    Log::info('Deposit confirmed', [
                        'deposit_id' => $deposit->id,
                        'user_id' => $deposit->user_id,
                        'amount' => $deposit->amount,
                        'confirmations' => $confirmations
                    ]);
                }

            } catch (\Exception $e) {
                DB::rollBack();
                
                Log::error('Failed to update pending deposit', [
                    'deposit_id' => $deposit->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $confirmedCount;
    }

    /**
     * Credit user wallet with deposit amount
     */
    private function creditUserWallet(int $userId, string $currency, float $amount): void
    {
        // Find or create user wallet
        $wallet = Wallet::firstOrCreate(
            [
                'user_id' => $userId,
                'cryptocurrency_symbol' => $currency
            ],
            [
                'balance' => 0,
                'locked_balance' => 0
            ]
        );

        // Add to balance
        $wallet->increment('balance', $amount);

        Log::info('User wallet credited', [
            'user_id' => $userId,
            'currency' => $currency,
            'amount' => $amount,
            'new_balance' => $wallet->fresh()->balance
        ]);
    }

    /**
     * Send deposit confirmation notification
     */
    private function sendDepositNotification(Deposit $deposit): void
    {
        try {
            $user = User::find($deposit->user_id);
            if ($user && $user->email) {
                // You can implement email notification here
                // Mail::to($user->email)->send(new DepositConfirmedMail($deposit));
                
                Log::info('Deposit notification sent', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'deposit_id' => $deposit->id
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send deposit notification', [
                'deposit_id' => $deposit->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get deposit statistics
     */
    public function getDepositStatistics(): array
    {
        return [
            'total_deposits' => Deposit::where('currency', 'ETH')->count(),
            'pending_deposits' => Deposit::where('currency', 'ETH')->where('status', 'pending')->count(),
            'completed_deposits' => Deposit::where('currency', 'ETH')->where('status', 'completed')->count(),
            'total_amount' => Deposit::where('currency', 'ETH')->where('status', 'completed')->sum('amount'),
            'last_24h_deposits' => Deposit::where('currency', 'ETH')
                ->where('created_at', '>=', now()->subDay())
                ->count(),
            'last_24h_amount' => Deposit::where('currency', 'ETH')
                ->where('status', 'completed')
                ->where('created_at', '>=', now()->subDay())
                ->sum('amount')
        ];
    }

    /**
     * Reprocess failed deposits
     */
    public function reprocessFailedDeposits(): array
    {
        $failedDeposits = Deposit::where('status', 'failed')
            ->where('currency', 'ETH')
            ->get();

        $results = [
            'total' => $failedDeposits->count(),
            'reprocessed' => 0,
            'failed' => 0
        ];

        foreach ($failedDeposits as $deposit) {
            try {
                // Get transaction details from blockchain
                $tx = $this->ethereumService->getTransactionByHash($deposit->txid);
                
                if ($tx) {
                    // Reprocess the deposit
                    $depositAddress = DepositAddress::where('address', $deposit->wallet_address)
                        ->where('user_id', $deposit->user_id)
                        ->first();

                    if ($depositAddress) {
                        $result = $this->processEthereumDeposit($depositAddress, [
                            'hash' => $tx['hash'],
                            'from' => $tx['from'],
                            'to' => $tx['to'],
                            'value' => $this->ethereumService->weiToEther($tx['value']),
                            'blockNumber' => hexdec($tx['blockNumber'])
                        ]);

                        if ($result['success']) {
                            $results['reprocessed']++;
                        } else {
                            $results['failed']++;
                        }
                    }
                }
            } catch (\Exception $e) {
                $results['failed']++;
                Log::error('Failed to reprocess deposit', [
                    'deposit_id' => $deposit->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }
}