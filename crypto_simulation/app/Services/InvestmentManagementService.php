<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\User;
use App\Models\Cryptocurrency;
use App\Models\TransactionRecord;
use App\Services\WalletManager;
use Illuminate\Support\Facades\DB;

class InvestmentManagementService
{
    private WalletManager $walletManager;

    public function __construct(WalletManager $walletManager)
    {
        $this->walletManager = $walletManager;
    }

    /**
     * Get all investments with filtering
     */
    public function getInvestments(array $filters = []): array
    {
        $query = Investment::with(['user', 'cryptocurrency'])
            ->orderBy('created_at', 'desc');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['investment_type'])) {
            $query->where('investment_type', $filters['investment_type']);
        }

        if (isset($filters['cryptocurrency_symbol'])) {
            $query->where('cryptocurrency_symbol', $filters['cryptocurrency_symbol']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('user', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $investments = $query->paginate(20);

        return [
            'investments' => $investments->items(),
            'pagination' => [
                'current_page' => $investments->currentPage(),
                'last_page' => $investments->lastPage(),
                'per_page' => $investments->perPage(),
                'total' => $investments->total(),
            ]
        ];
    }

    /**
     * Create new investment
     */
    public function createInvestment(int $userId, array $data): array
    {
        try {
            DB::beginTransaction();

            $user = User::findOrFail($userId);
            $crypto = Cryptocurrency::where('symbol', $data['cryptocurrency_symbol'])->firstOrFail();

            // Check if user has sufficient balance
            $wallet = $user->wallets()->where('cryptocurrency_symbol', $data['cryptocurrency_symbol'])->first();
            if (!$wallet || $wallet->balance < $data['amount']) {
                return [
                    'success' => false,
                    'message' => 'Insufficient balance'
                ];
            }

            // Calculate maturity date
            $startDate = now();
            $maturityDate = $startDate->copy()->addDays($data['duration_days']);

            // Create investment
            $investment = Investment::create([
                'user_id' => $userId,
                'cryptocurrency_symbol' => $data['cryptocurrency_symbol'],
                'investment_type' => $data['investment_type'],
                'amount' => $data['amount'],
                'duration_days' => $data['duration_days'],
                'expected_return_rate' => $data['expected_return_rate'],
                'current_value' => $data['amount'], // Initial value equals investment amount
                'status' => 'active',
                'started_at' => $startDate,
                'maturity_date' => $maturityDate,
            ]);

            // Deduct amount from user's wallet
            $this->walletManager->updateBalance(
                $userId,
                $data['cryptocurrency_symbol'],
                -$data['amount'],
                'investment'
            );

            // Create transaction record
            TransactionRecord::create([
                'user_id' => $userId,
                'type' => 'investment',
                'cryptocurrency_symbol' => $data['cryptocurrency_symbol'],
                'amount' => $data['amount'],
                'status' => 'completed',
                'description' => "Investment in {$data['investment_type']} for {$data['duration_days']} days",
                'reference_id' => $investment->id,
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Investment created successfully',
                'investment_id' => $investment->id
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to create investment: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cancel investment (early withdrawal)
     */
    public function cancelInvestment(int $investmentId, float $penaltyRate = 0.1): array
    {
        try {
            DB::beginTransaction();

            $investment = Investment::findOrFail($investmentId);

            if ($investment->status !== 'active') {
                return [
                    'success' => false,
                    'message' => 'Investment is not active'
                ];
            }

            // Calculate penalty
            $penalty = $investment->current_value * $penaltyRate;
            $returnAmount = $investment->current_value - $penalty;

            // Update investment status
            $investment->update([
                'status' => 'cancelled',
                'completed_at' => now(),
            ]);

            // Return amount to user's wallet (minus penalty)
            $this->walletManager->updateBalance(
                $investment->user_id,
                $investment->cryptocurrency_symbol,
                $returnAmount,
                'investment_cancellation'
            );

            // Create transaction records
            TransactionRecord::create([
                'user_id' => $investment->user_id,
                'type' => 'withdrawal',
                'cryptocurrency_symbol' => $investment->cryptocurrency_symbol,
                'amount' => $returnAmount,
                'status' => 'completed',
                'description' => "Early withdrawal from {$investment->investment_type} (penalty: {$penalty})",
                'reference_id' => $investment->id,
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Investment cancelled successfully',
                'return_amount' => $returnAmount,
                'penalty' => $penalty
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to cancel investment: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Mature investment (complete at maturity)
     */
    public function matureInvestment(int $investmentId): array
    {
        try {
            DB::beginTransaction();

            $investment = Investment::findOrFail($investmentId);

            if ($investment->status !== 'active') {
                return [
                    'success' => false,
                    'message' => 'Investment is not active'
                ];
            }

            // Calculate final return
            $daysInvested = $investment->started_at->diffInDays(now());
            $annualReturn = $investment->amount * ($investment->expected_return_rate / 100);
            $actualReturn = ($annualReturn / 365) * $daysInvested;
            $finalAmount = $investment->amount + $actualReturn;

            // Update investment status
            $investment->update([
                'status' => 'matured',
                'current_value' => $finalAmount,
                'completed_at' => now(),
            ]);

            // Return amount to user's wallet
            $this->walletManager->updateBalance(
                $investment->user_id,
                $investment->cryptocurrency_symbol,
                $finalAmount,
                'investment_maturity'
            );

            // Create transaction record
            TransactionRecord::create([
                'user_id' => $investment->user_id,
                'type' => 'deposit',
                'cryptocurrency_symbol' => $investment->cryptocurrency_symbol,
                'amount' => $finalAmount,
                'status' => 'completed',
                'description' => "Investment maturity return from {$investment->investment_type} (profit: {$actualReturn})",
                'reference_id' => $investment->id,
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Investment matured successfully',
                'final_amount' => $finalAmount,
                'profit' => $actualReturn
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to mature investment: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update investment current value (for admin adjustments)
     */
    public function updateInvestmentValue(int $investmentId, float $newValue, string $reason): array
    {
        try {
            $investment = Investment::findOrFail($investmentId);
            $oldValue = $investment->current_value;

            $investment->update(['current_value' => $newValue]);

            // Log the adjustment
            TransactionRecord::create([
                'user_id' => $investment->user_id,
                'type' => 'admin_adjustment',
                'cryptocurrency_symbol' => $investment->cryptocurrency_symbol,
                'amount' => $newValue - $oldValue,
                'description' => "Investment value adjustment: {$oldValue} -> {$newValue}. Reason: {$reason}",
                'reference_id' => $investment->id,
            ]);

            return [
                'success' => true,
                'message' => 'Investment value updated successfully',
                'old_value' => $oldValue,
                'new_value' => $newValue
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update investment value: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get investment statistics
     */
    public function getInvestmentStatistics(): array
    {
        $totalInvestments = Investment::count();
        $activeInvestments = Investment::where('status', 'active')->count();

        return [
            'total_investments' => $totalInvestments,
            'active_investments' => $activeInvestments,
            'completed_investments' => Investment::where('status', 'matured')->count(),
            'cancelled_investments' => Investment::where('status', 'cancelled')->count(),
            'total_invested_amount' => Investment::sum('amount'),
            'total_current_value' => Investment::where('status', 'active')->sum('current_value'),
            'avg_investment_amount' => Investment::avg('amount'),
            'avg_return_rate' => Investment::avg('expected_return_rate'),
            'investments_by_type' => $this->getInvestmentsByType(),
            'investments_by_crypto' => $this->getInvestmentsByCrypto(),
            'monthly_investment_trend' => $this->getMonthlyInvestmentTrend(),
        ];
    }

    /**
     * Get investments grouped by type
     */
    private function getInvestmentsByType(): array
    {
        return Investment::select('investment_type', DB::raw('count(*) as count'), DB::raw('sum(amount) as total_amount'))
            ->groupBy('investment_type')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->investment_type => [
                    'count' => $item->count,
                    'total_amount' => $item->total_amount
                ]];
            })
            ->toArray();
    }

    /**
     * Get investments grouped by cryptocurrency
     */
    private function getInvestmentsByCrypto(): array
    {
        return Investment::select('cryptocurrency_symbol', DB::raw('count(*) as count'), DB::raw('sum(amount) as total_amount'))
            ->groupBy('cryptocurrency_symbol')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->cryptocurrency_symbol => [
                    'count' => $item->count,
                    'total_amount' => $item->total_amount
                ]];
            })
            ->toArray();
    }

    /**
     * Get monthly investment trend
     */
    private function getMonthlyInvestmentTrend(): array
    {
        $months = [];
        $amounts = [];
        $counts = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = $date->format('M Y');
            
            $monthlyData = Investment::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->selectRaw('count(*) as count, sum(amount) as total_amount')
                ->first();
                
            $amounts[] = $monthlyData->total_amount ?? 0;
            $counts[] = $monthlyData->count ?? 0;
        }

        return [
            'labels' => $months,
            'amounts' => $amounts,
            'counts' => $counts,
        ];
    }

    /**
     * Process daily investment returns (to be run via cron)
     */
    public function processDailyReturns(): array
    {
        try {
            $activeInvestments = Investment::where('status', 'active')->get();
            $processedCount = 0;

            foreach ($activeInvestments as $investment) {
                // Calculate daily return
                $annualReturn = $investment->amount * ($investment->expected_return_rate / 100);
                $dailyReturn = $annualReturn / 365;
                
                // Update current value
                $investment->increment('current_value', $dailyReturn);
                $processedCount++;

                // Check if investment has matured
                if (now()->gte($investment->maturity_date)) {
                    $this->matureInvestment($investment->id);
                }
            }

            return [
                'success' => true,
                'message' => "Processed daily returns for {$processedCount} investments"
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to process daily returns: ' . $e->getMessage()
            ];
        }
    }
}