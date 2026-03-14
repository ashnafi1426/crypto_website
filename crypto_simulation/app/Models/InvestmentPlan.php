<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvestmentPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'crypto_type',
        'min_amount',
        'max_amount',
        'roi_percentage',
        'duration_days',
        'type',
        'active',
        'features',
        'max_investors',
        'current_investors',
        'total_invested'
    ];

    protected $casts = [
        'min_amount' => 'decimal:8',
        'max_amount' => 'decimal:8',
        'roi_percentage' => 'decimal:2',
        'active' => 'boolean',
        'features' => 'array',
        'total_invested' => 'decimal:8'
    ];

    /**
     * Get the investments for this plan.
     */
    public function investments(): HasMany
    {
        return $this->hasMany(UserInvestment::class, 'plan_id');
    }

    /**
     * Get active investments for this plan.
     */
    public function activeInvestments(): HasMany
    {
        return $this->hasMany(UserInvestment::class, 'plan_id')->where('status', 'active');
    }

    /**
     * Check if plan is available for new investments.
     */
    public function isAvailable(): bool
    {
        if (!$this->active) {
            return false;
        }

        if ($this->max_investors && $this->current_investors >= $this->max_investors) {
            return false;
        }

        return true;
    }

    /**
     * Calculate daily profit for given amount.
     */
    public function calculateDailyProfit(string $amount): string
    {
        return bcmul($amount, bcdiv($this->roi_percentage, '100', 8), 8);
    }

    /**
     * Calculate total profit for full duration.
     */
    public function calculateTotalProfit(string $amount): string
    {
        $dailyProfit = $this->calculateDailyProfit($amount);
        return bcmul($dailyProfit, (string)$this->duration_days, 8);
    }

    /**
     * Get plan performance metrics.
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'total_investors' => $this->current_investors,
            'total_invested' => $this->total_invested,
            'average_investment' => $this->current_investors > 0 
                ? bcdiv($this->total_invested, (string)$this->current_investors, 8) 
                : '0.00000000',
            'availability' => $this->isAvailable(),
            'roi_per_duration' => bcmul($this->roi_percentage, (string)$this->duration_days, 2)
        ];
    }

    /**
     * Scope for active plans.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope for specific crypto type.
     */
    public function scopeForCrypto($query, string $cryptoType)
    {
        return $query->where('crypto_type', $cryptoType);
    }
}