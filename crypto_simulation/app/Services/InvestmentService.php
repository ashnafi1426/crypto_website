<?php

namespace App\Services;

use App\Models\User;
use App\Models\InvestmentPlan;
use App\Models\UserInvestment;
use App\Models\InvestmentDistribution;
use App\Services\Contracts\WalletManagerInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class InvestmentService
{
    private WalletManagerInterface $walletManager;

    public function __construct(WalletManagerInterface $walletManager)
    {
        $this->walletManager = $walletManager;
    }

    /**
     * Get all available investment plans.
     */
    public function getAvailablePlans(?string $cryptoType = null): array
    {
        try {
            $query = InvestmentPlan::active();
            
            if ($cryptoType) {
                $query->forCrypto($cryptoType);
            }

            $plans = $query->orderBy('roi_percentage', 'desc')->get();

            $plansData = [];
            foreach ($plans as $plan) {
                $plansData[] = [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'crypto_type' => $plan->crypto_type,
                    'minimum_amount' => $plan->minimum_amount,
                    'maximum_amount' => $plan->maximum_amount,
                    'roi_percentage' => $plan->roi_percentage,
                    'duration_days' => $plan->duration_days,
                    'risk_level' => $plan->risk_level,
                    'is_active' => $plan->is_active,
                    'created_at' => $plan->created_at,
                ];
            }

            return [
                'success' => true,
                'plans' => $plansData,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get investment plans: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to retrieve investment plans.',
            ];
        }
    }

    /**
     * Create a new investment for a user.
     */
    public function createInvestment(User $user, int $planId, string $amount): array
    {
        try {
            DB::beginTransaction();

            // Get investment plan
            $plan = InvestmentPlan::active()->find($planId);
            if (!$plan) {
                return [
                    'success' => false,
                    'message' => 'Investment plan not found or inactive.',
                ];
            }

            // Validate amount
            $amountDecimal = bcmul($amount, '1', 8);
            if (bccomp($amountDecimal, $plan->minimum_amount, 8) < 0) {
                return [
                    'success' => false,
                    'message' => "Minimum investment amount is {$plan->minimum_amount} {$plan->crypto_type}.",
                ];
            }

            if ($plan->maximum_amount && bccomp($amountDecimal, $plan->maximum_amount, 8) > 0) {
                return [
                    'success' => false,
                    'message' => "Maximum investment amount is {$plan->maximum_amount} {$plan->crypto_type}.",
                ];
            }

            // Check user wallet balance
            $wallet = $this->walletManager->getWallet($user, $plan->crypto_type);
            if (!$wallet || bccomp($wallet->balance, $amountDecimal, 8) < 0) {
                return [
                    'success' => false,
                    'message' => 'Insufficient balance in your wallet.',
                ];
            }

            // Deduct from wallet
            $deductResult = $this->walletManager->debitWallet(
                $user, 
                $plan->crypto_type, 
                $amountDecimal, 
                'investment', 
                "Investment in {$plan->name}"
            );
            if (!$deductResult['success']) {
                return $deductResult;
            }

            // Calculate expected return
            $expectedReturn = bcmul($amountDecimal, bcdiv($plan->roi_percentage, '100', 8), 8);
            $totalReturn = bcadd($amountDecimal, $expectedReturn, 8);

            // Create investment record
            $investment = UserInvestment::create([
                'user_id' => $user->id,
                'investment_plan_id' => $plan->id,
                'amount' => $amountDecimal,
                'expected_return' => $expectedReturn,
                'total_return' => $totalReturn,
                'start_date' => now(),
                'end_date' => now()->addDays($plan->duration_days),
                'status' => 'active',
                'reference_id' => 'INV-' . strtoupper(Str::random(10)),
            ]);

            DB::commit();

            Log::info('Investment created successfully', [
                'user_id' => $user->id,
                'investment_id' => $investment->id,
                'plan_id' => $plan->id,
                'amount' => $amountDecimal,
            ]);

            return [
                'success' => true,
                'message' => 'Investment created successfully.',
                'investment' => [
                    'id' => $investment->id,
                    'reference_id' => $investment->reference_id,
                    'amount' => $investment->amount,
                    'expected_return' => $investment->expected_return,
                    'total_return' => $investment->total_return,
                    'start_date' => $investment->start_date,
                    'end_date' => $investment->end_date,
                    'status' => $investment->status,
                    'plan' => [
                        'name' => $plan->name,
                        'crypto_type' => $plan->crypto_type,
                        'roi_percentage' => $plan->roi_percentage,
                        'duration_days' => $plan->duration_days,
                    ],
                ],
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create investment: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create investment.',
            ];
        }
    }

    /**
     * Get user's investments.
     */
    public function getUserInvestments(User $user, ?string $status = null): array
    {
        try {
            $query = UserInvestment::with('investmentPlan')
                ->where('user_id', $user->id);

            if ($status) {
                $query->where('status', $status);
            }

            $investments = $query->orderBy('created_at', 'desc')->get();

            $investmentsData = [];
            foreach ($investments as $investment) {
                $investmentsData[] = [
                    'id' => $investment->id,
                    'reference_id' => $investment->reference_id,
                    'amount' => $investment->amount,
                    'expected_return' => $investment->expected_return,
                    'total_return' => $investment->total_return,
                    'start_date' => $investment->start_date,
                    'end_date' => $investment->end_date,
                    'status' => $investment->status,
                    'created_at' => $investment->created_at,
                    'plan' => [
                        'name' => $investment->investmentPlan->name,
                        'crypto_type' => $investment->investmentPlan->crypto_type,
                        'roi_percentage' => $investment->investmentPlan->roi_percentage,
                        'duration_days' => $investment->investmentPlan->duration_days,
                        'risk_level' => $investment->investmentPlan->risk_level,
                    ],
                ];
            }

            return [
                'success' => true,
                'investments' => $investmentsData,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get user investments: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to retrieve investments.',
            ];
        }
    }

    /**
     * Process investment maturity and distribute returns.
     */
    public function processMaturedInvestments(): array
    {
        try {
            $maturedInvestments = UserInvestment::with(['user', 'investmentPlan'])
                ->where('status', 'active')
                ->where('end_date', '<=', now())
                ->get();

            $processedCount = 0;
            $errors = [];

            foreach ($maturedInvestments as $investment) {
                try {
                    DB::beginTransaction();

                    // Add returns to user wallet
                    $addResult = $this->walletManager->creditWallet(
                        $investment->user,
                        $investment->investmentPlan->crypto_type,
                        $investment->total_return,
                        'investment_return',
                        "Investment return for {$investment->reference_id}"
                    );

                    if ($addResult['success']) {
                        // Update investment status
                        UserInvestment::where('id', $investment->id)->update(['status' => 'completed']);

                        // Create distribution record
                        InvestmentDistribution::create([
                            'user_investment_id' => $investment->id,
                            'user_id' => $investment->user_id,
                            'amount' => $investment->total_return,
                            'crypto_type' => $investment->investmentPlan->crypto_type,
                            'distributed_at' => now(),
                        ]);

                        $processedCount++;
                        DB::commit();

                        Log::info('Investment matured and processed', [
                            'investment_id' => $investment->id,
                            'user_id' => $investment->user_id,
                            'amount' => $investment->total_return,
                        ]);
                    } else {
                        DB::rollBack();
                        $errors[] = "Failed to process investment {$investment->id}: {$addResult['message']}";
                    }

                } catch (\Exception $e) {
                    DB::rollBack();
                    $errors[] = "Failed to process investment {$investment->id}: {$e->getMessage()}";
                }
            }

            return [
                'success' => true,
                'processed_count' => $processedCount,
                'total_matured' => $maturedInvestments->count(),
                'errors' => $errors,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to process matured investments: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to process matured investments.',
            ];
        }
    }

    /**
     * Cancel an active investment.
     */
    public function cancelInvestment(User $user, int $investmentId): array
    {
        try {
            DB::beginTransaction();

            $investment = UserInvestment::with('investmentPlan')
                ->where('id', $investmentId)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            if (!$investment) {
                return [
                    'success' => false,
                    'message' => 'Investment not found or cannot be cancelled.',
                ];
            }

            // Calculate penalty (e.g., 10% penalty for early withdrawal)
            $penaltyPercentage = '10.00';
            $penalty = bcmul($investment->amount, bcdiv($penaltyPercentage, '100', 8), 8);
            $refundAmount = bcsub($investment->amount, $penalty, 8);

            // Refund to wallet
            $refundResult = $this->walletManager->creditWallet(
                $user,
                $investment->investmentPlan->crypto_type,
                $refundAmount,
                'investment_refund',
                "Refund for cancelled investment {$investment->reference_id}"
            );

            if (!$refundResult['success']) {
                DB::rollBack();
                return $refundResult;
            }

            // Update investment status
            UserInvestment::where('id', $investment->id)->update(['status' => 'cancelled']);

            DB::commit();

            Log::info('Investment cancelled', [
                'investment_id' => $investment->id,
                'user_id' => $user->id,
                'refund_amount' => $refundAmount,
                'penalty' => $penalty,
            ]);

            return [
                'success' => true,
                'message' => 'Investment cancelled successfully.',
                'refund_amount' => $refundAmount,
                'penalty' => $penalty,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to cancel investment: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to cancel investment.',
            ];
        }
    }

    /**
     * Get investment statistics for a user.
     */
    public function getUserInvestmentStats(User $user): array
    {
        try {
            $stats = [
                'total_invested' => '0.00000000',
                'total_returns' => '0.00000000',
                'active_investments' => 0,
                'completed_investments' => 0,
                'cancelled_investments' => 0,
            ];

            $investments = UserInvestment::where('user_id', $user->id)->get();

            foreach ($investments as $investment) {
                $stats['total_invested'] = bcadd($stats['total_invested'], $investment->amount, 8);

                if ($investment->status === 'completed') {
                    $stats['total_returns'] = bcadd($stats['total_returns'], $investment->expected_return, 8);
                    $stats['completed_investments']++;
                } elseif ($investment->status === 'active') {
                    $stats['active_investments']++;
                } elseif ($investment->status === 'cancelled') {
                    $stats['cancelled_investments']++;
                }
            }

            return [
                'success' => true,
                'stats' => $stats,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get investment stats: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to retrieve investment statistics.',
            ];
        }
    }
}
               