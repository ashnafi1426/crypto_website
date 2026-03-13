<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\Cryptocurrency;
use App\Models\TransactionRecord;
use App\Services\Contracts\WalletManagerInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WalletManager implements WalletManagerInterface
{
    /**
     * Get balance for user and cryptocurrency.
     */
    public function getBalance(int $userId, string $cryptocurrency): array
    {
        try {
            $wallet = Wallet::where('user_id', $userId)
                ->where('cryptocurrency_symbol', $cryptocurrency)
                ->first();

            if (!$wallet) {
                return [
                    'success' => false,
                    'message' => 'Wallet not found',
                    'balance' => '0.00000000',
                    'reserved_balance' => '0.00000000',
                    'available_balance' => '0.00000000'
                ];
            }

            $availableBalance = bcsub($wallet->balance, $wallet->reserved_balance, 8);

            return [
                'success' => true,
                'balance' => $wallet->balance,
                'reserved_balance' => $wallet->reserved_balance,
                'available_balance' => $availableBalance,
                'cryptocurrency' => $cryptocurrency
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get balance', [
                'user_id' => $userId,
                'cryptocurrency' => $cryptocurrency,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve balance',
                'balance' => '0.00000000',
                'reserved_balance' => '0.00000000',
                'available_balance' => '0.00000000'
            ];
        }
    }

    /**
     * Update balance for user and cryptocurrency with transaction logging.
     */
    public function updateBalance(int $userId, string $cryptocurrency, string $amount, string $reason, ?string $description = null): array
    {
        try {
            DB::beginTransaction();

            $wallet = Wallet::where('user_id', $userId)
                ->where('cryptocurrency_symbol', $cryptocurrency)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                // Create wallet if it doesn't exist
                $wallet = Wallet::create([
                    'user_id' => $userId,
                    'cryptocurrency_symbol' => $cryptocurrency,
                    'balance' => '0.00000000',
                    'reserved_balance' => '0.00000000'
                ]);
            }

            $oldBalance = $wallet->balance;
            $newBalance = bcadd($wallet->balance, $amount, 8);

            // Prevent negative balance
            if (bccomp($newBalance, '0', 8) < 0) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Insufficient balance',
                    'current_balance' => $oldBalance
                ];
            }

            // Update wallet balance
            $wallet->update(['balance' => $newBalance]);

            // Create transaction record for audit trail
            TransactionRecord::create([
                'user_id' => $userId,
                'cryptocurrency_symbol' => $cryptocurrency,
                'type' => bccomp($amount, '0', 8) >= 0 ? 'deposit' : 'withdrawal',
                'amount' => abs($amount),
                'status' => 'completed',
                'description' => $description ?? $reason,
                'reference_id' => Str::uuid(),
                'processed_at' => now()
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Balance updated successfully',
                'old_balance' => $oldBalance,
                'new_balance' => $newBalance,
                'amount_changed' => $amount
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update balance', [
                'user_id' => $userId,
                'cryptocurrency' => $cryptocurrency,
                'amount' => $amount,
                'reason' => $reason,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update balance due to server error'
            ];
        }
    }

    /**
     * Get portfolio value for user using current market prices.
     */
    public function getPortfolioValue(int $userId): array
    {
        try {
            $wallets = Wallet::where('user_id', $userId)
                ->where('balance', '>', '0')
                ->get();

            $totalValue = '0.00000000';
            $portfolioBreakdown = [];

            foreach ($wallets as $wallet) {
                $crypto = Cryptocurrency::where('symbol', $wallet->cryptocurrency_symbol)->first();
                
                if (!$crypto) {
                    continue;
                }

                // For USD, value is 1:1
                if ($wallet->cryptocurrency_symbol === 'USD') {
                    $value = $wallet->balance;
                } else {
                    // Calculate value in USD
                    $value = bcmul($wallet->balance, $crypto->current_price, 8);
                }

                $totalValue = bcadd($totalValue, $value, 8);

                $portfolioBreakdown[] = [
                    'cryptocurrency' => $wallet->cryptocurrency_symbol,
                    'balance' => $wallet->balance,
                    'price' => $crypto->current_price,
                    'value_usd' => $value,
                    'percentage' => '0.00' // Will calculate after total
                ];
            }

            // Calculate percentages
            foreach ($portfolioBreakdown as &$item) {
                if (bccomp($totalValue, '0', 8) > 0) {
                    $percentage = bcdiv(bcmul($item['value_usd'], '100', 8), $totalValue, 2);
                    $item['percentage'] = $percentage;
                }
            }

            return [
                'success' => true,
                'total_value' => $totalValue,
                'currency' => 'USD',
                'portfolio_breakdown' => $portfolioBreakdown,
                'last_updated' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error('Failed to calculate portfolio value', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to calculate portfolio value',
                'total_value' => '0.00000000'
            ];
        }
    }

    /**
     * Reserve balance for pending orders.
     */
    public function reserveBalance(int $userId, string $cryptocurrency, string $amount): string
    {
        try {
            DB::beginTransaction();

            $wallet = Wallet::where('user_id', $userId)
                ->where('cryptocurrency_symbol', $cryptocurrency)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                DB::rollBack();
                return '';
            }

            $availableBalance = bcsub($wallet->balance, $wallet->reserved_balance, 8);

            // Check if sufficient balance available
            if (bccomp($availableBalance, $amount, 8) < 0) {
                DB::rollBack();
                return '';
            }

            // Update reserved balance
            $newReservedBalance = bcadd($wallet->reserved_balance, $amount, 8);
            $wallet->update(['reserved_balance' => $newReservedBalance]);

            // Generate reservation ID
            $reservationId = Str::uuid();

            // Create transaction record for reservation
            TransactionRecord::create([
                'user_id' => $userId,
                'cryptocurrency_symbol' => $cryptocurrency,
                'transaction_type' => 'reserve',
                'amount' => $amount,
                'balance_before' => $wallet->balance,
                'balance_after' => $wallet->balance, // Balance doesn't change, only reserved
                'reason' => 'Balance reserved for order',
                'description' => 'Balance reserved for order',
                'reference_id' => $reservationId,
                'created_at' => now()
            ]);

            DB::commit();

            return $reservationId;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to reserve balance', [
                'user_id' => $userId,
                'cryptocurrency' => $cryptocurrency,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);

            return '';
        }
    }

    /**
     * Release reserved balance.
     */
    public function releaseReservation(string $reservationId): bool
    {
        try {
            DB::beginTransaction();

            // Find the reservation transaction
            $reservationRecord = TransactionRecord::where('reference_id', $reservationId)
                ->where('transaction_type', 'reserve')
                ->first();

            if (!$reservationRecord) {
                DB::rollBack();
                return false;
            }

            $wallet = Wallet::where('user_id', $reservationRecord->user_id)
                ->where('cryptocurrency_symbol', $reservationRecord->cryptocurrency_symbol)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                DB::rollBack();
                return false;
            }

            // Release the reserved amount
            $newReservedBalance = bcsub($wallet->reserved_balance, $reservationRecord->amount, 8);
            
            // Ensure reserved balance doesn't go negative
            if (bccomp($newReservedBalance, '0', 8) < 0) {
                $newReservedBalance = '0.00000000';
            }

            $wallet->update(['reserved_balance' => $newReservedBalance]);

            // Create transaction record for release
            TransactionRecord::create([
                'user_id' => $reservationRecord->user_id,
                'cryptocurrency_symbol' => $reservationRecord->cryptocurrency_symbol,
                'transaction_type' => 'release',
                'amount' => $reservationRecord->amount,
                'balance_before' => $wallet->balance,
                'balance_after' => $wallet->balance,
                'reason' => 'Reserved balance released',
                'description' => 'Reserved balance released',
                'reference_id' => "{$reservationId}_release",
                'created_at' => now()
            ]);

            DB::commit();

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to release reservation', [
                'reservation_id' => $reservationId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get all wallets for a user.
     */
    public function getUserWallets(int $userId): array
    {
        try {
            $wallets = Wallet::where('user_id', $userId)
                ->with('cryptocurrency')
                ->get();

            $walletsData = [];

            foreach ($wallets as $wallet) {
                $crypto = $wallet->cryptocurrency;
                $availableBalance = bcsub($wallet->balance, $wallet->reserved_balance, 8);

                $walletsData[] = [
                    'cryptocurrency' => $wallet->cryptocurrency_symbol,
                    'name' => $crypto ? $crypto->name : $wallet->cryptocurrency_symbol,
                    'balance' => $wallet->balance,
                    'reserved_balance' => $wallet->reserved_balance,
                    'available_balance' => $availableBalance,
                    'current_price' => $crypto ? $crypto->current_price : '0.00000000',
                    'value_usd' => $crypto && $wallet->cryptocurrency_symbol !== 'USD' 
                        ? bcmul($wallet->balance, $crypto->current_price, 8) 
                        : $wallet->balance
                ];
            }

            return [
                'success' => true,
                'wallets' => $walletsData
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get user wallets', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve wallets',
                'wallets' => []
            ];
        }
    }
}