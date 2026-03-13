<?php

namespace App\Services;

use App\Models\User;
use App\Models\ReferralProgram;
use App\Models\TransactionRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReferralManagementService
{
    /**
     * Get all referral programs with filtering
     */
    public function getReferralPrograms(array $filters = []): array
    {
        $query = ReferralProgram::with(['user'])
            ->orderBy('total_earned', 'desc');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('referral_code', 'like', "%{$search}%")
                  ->orWhereHas('user', function($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%")
                               ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $programs = $query->paginate(20);

        return [
            'programs' => $programs->items(),
            'pagination' => [
                'current_page' => $programs->currentPage(),
                'last_page' => $programs->lastPage(),
                'per_page' => $programs->perPage(),
                'total' => $programs->total(),
            ]
        ];
    }

    /**
     * Create referral program for user
     */
    public function createReferralProgram(int $userId, float $commissionRate = 0.05): array
    {
        try {
            $user = User::findOrFail($userId);

            // Check if user already has a referral program
            if ($user->referralProgram) {
                return [
                    'success' => false,
                    'message' => 'User already has a referral program'
                ];
            }

            $referralCode = $this->generateUniqueReferralCode();

            $program = ReferralProgram::create([
                'user_id' => $userId,
                'referral_code' => $referralCode,
                'commission_rate' => $commissionRate,
                'status' => 'active',
            ]);

            return [
                'success' => true,
                'message' => 'Referral program created successfully',
                'referral_code' => $referralCode,
                'program_id' => $program->id
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create referral program: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update commission rate
     */
    public function updateCommissionRate(int $programId, float $newRate): array
    {
        try {
            $program = ReferralProgram::findOrFail($programId);
            
            $oldRate = $program->commission_rate;
            $program->update(['commission_rate' => $newRate]);

            // Log the change
            TransactionRecord::create([
                'user_id' => $program->user_id,
                'type' => 'admin_adjustment',
                'cryptocurrency_symbol' => 'USD',
                'amount' => 0,
                'description' => "Commission rate changed from {$oldRate}% to {$newRate}%",
            ]);

            return [
                'success' => true,
                'message' => 'Commission rate updated successfully',
                'old_rate' => $oldRate,
                'new_rate' => $newRate
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update commission rate: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process referral commission
     */
    public function processReferralCommission(int $referredUserId, float $tradingFee): array
    {
        try {
            $referredUser = User::find($referredUserId);
            
            if (!$referredUser || !$referredUser->referred_by) {
                return ['success' => false, 'message' => 'No referrer found'];
            }

            $referrer = User::find($referredUser->referred_by);
            $referralProgram = $referrer->referralProgram;

            if (!$referralProgram || $referralProgram->status !== 'active') {
                return ['success' => false, 'message' => 'Referral program not active'];
            }

            $commission = $tradingFee * $referralProgram->commission_rate;

            DB::beginTransaction();

            // Update referral program earnings
            $referralProgram->increment('total_earned', $commission);
            $referralProgram->increment('pending_payout', $commission);

            // Create transaction record
            TransactionRecord::create([
                'user_id' => $referrer->id,
                'type' => 'referral_commission',
                'cryptocurrency_symbol' => 'USD',
                'amount' => $commission,
                'status' => 'completed',
                'description' => "Referral commission from {$referredUser->name}",
                'reference_id' => $referredUserId,
            ]);

            DB::commit();

            return [
                'success' => true,
                'commission' => $commission,
                'referrer_id' => $referrer->id
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to process referral commission: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process referral payout
     */
    public function processReferralPayout(int $programId, float $amount): array
    {
        try {
            DB::beginTransaction();

            $program = ReferralProgram::findOrFail($programId);

            if ($amount > $program->pending_payout) {
                return [
                    'success' => false,
                    'message' => 'Payout amount exceeds pending balance'
                ];
            }

            // Update pending payout
            $program->decrement('pending_payout', $amount);

            // Create payout transaction
            TransactionRecord::create([
                'user_id' => $program->user_id,
                'type' => 'withdrawal',
                'cryptocurrency_symbol' => 'USD',
                'amount' => $amount,
                'status' => 'completed',
                'description' => 'Referral commission payout',
                'processed_at' => now(),
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Referral payout processed successfully',
                'amount' => $amount
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to process payout: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Suspend referral program
     */
    public function suspendReferralProgram(int $programId, string $reason): array
    {
        try {
            $program = ReferralProgram::findOrFail($programId);
            
            $program->update(['status' => 'suspended']);

            // Log the suspension
            TransactionRecord::create([
                'user_id' => $program->user_id,
                'type' => 'admin_adjustment',
                'cryptocurrency_symbol' => 'USD',
                'amount' => 0,
                'description' => "Referral program suspended. Reason: {$reason}",
            ]);

            return [
                'success' => true,
                'message' => 'Referral program suspended successfully'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to suspend referral program: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Activate referral program
     */
    public function activateReferralProgram(int $programId): array
    {
        try {
            $program = ReferralProgram::findOrFail($programId);
            
            $program->update(['status' => 'active']);

            // Log the activation
            TransactionRecord::create([
                'user_id' => $program->user_id,
                'type' => 'admin_adjustment',
                'cryptocurrency_symbol' => 'USD',
                'amount' => 0,
                'description' => 'Referral program activated',
            ]);

            return [
                'success' => true,
                'message' => 'Referral program activated successfully'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to activate referral program: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get referral statistics
     */
    public function getReferralStatistics(): array
    {
        $totalPrograms = ReferralProgram::count();
        $activePrograms = ReferralProgram::where('status', 'active')->count();

        return [
            'total_programs' => $totalPrograms,
            'active_programs' => $activePrograms,
            'suspended_programs' => ReferralProgram::where('status', 'suspended')->count(),
            'total_referrals' => ReferralProgram::sum('total_referrals'),
            'active_referrals' => ReferralProgram::sum('active_referrals'),
            'total_commissions_paid' => ReferralProgram::sum('total_earned'),
            'pending_payouts' => ReferralProgram::sum('pending_payout'),
            'avg_commission_rate' => ReferralProgram::avg('commission_rate'),
            'top_referrers' => $this->getTopReferrers(),
        ];
    }

    /**
     * Get top referrers
     */
    public function getTopReferrers(int $limit = 10): array
    {
        return ReferralProgram::with('user')
            ->orderBy('total_earned', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($program) {
                return [
                    'user_name' => $program->user->name,
                    'referral_code' => $program->referral_code,
                    'total_referrals' => $program->total_referrals,
                    'total_earned' => $program->total_earned,
                    'commission_rate' => $program->commission_rate,
                ];
            })
            ->toArray();
    }

    /**
     * Generate unique referral code
     */
    private function generateUniqueReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (ReferralProgram::where('referral_code', $code)->exists());

        return $code;
    }

    /**
     * Update referral counts when user registers
     */
    public function updateReferralCounts(int $referrerId): void
    {
        $referralProgram = ReferralProgram::where('user_id', $referrerId)->first();
        
        if ($referralProgram) {
            $referralProgram->increment('total_referrals');
            $referralProgram->increment('active_referrals');
        }
    }

    /**
     * Update active referral count when user becomes inactive
     */
    public function decrementActiveReferrals(int $referrerId): void
    {
        $referralProgram = ReferralProgram::where('user_id', $referrerId)->first();
        
        if ($referralProgram && $referralProgram->active_referrals > 0) {
            $referralProgram->decrement('active_referrals');
        }
    }
}