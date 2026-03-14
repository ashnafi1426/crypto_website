<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class UserInvestment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'amount',
        'daily_profit',
        'total_profit',
        'total_withdrawn',
        'start_date',
        'end_date',
        'last_profit_date',
        'status',
        'auto_reinvest',
        'metadata'
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'daily_profit' => 'decimal:8',
        'total_profit' => 'decimal:8',
        'total_withdrawn' => 'decimal:8',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'last_profit_date' => 'datetime',
        'auto_reinvest' => 'boolean',
        'metadata' => 'array'
    ];

    /**
     * Get the user that owns the investment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the investment plan.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(InvestmentPlan::class, 'plan_id');
    }

    /**
     * Get the profit distributions for this investment.
     */
    public function distributions(): HasMany
    {
        return $this->hasMany(InvestmentDistribution::class, 'investment_id');
    }

    /**
     * Check if investment is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->end_date->isFuture();
    }

    /**
     * Check if investment has matured.
     */
    public function hasMatured(): bool
    {
        return $this->end_date->isPast();
    }

    /**
     * Get days remaining until maturity.
     */
    public function getDaysRemaining(): int
    {
        if ($this->hasMatured()) {
            return 0;
        }

        return $this->end_date->diffInDays(now());
    }

    /**
     * Get days since last profit distribution.
     */
    public function getDaysSinceLastProfit(): int
    {
        $lastProfitDate = $this->last_profit_date ?? $this->start_date;
        return $lastProfitDate->diffInDays(now());
    }

    /**
     * Check if profit is due.
     */
    public function isProfitDue(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        return $this->getDaysSinceLastProfit() >= 1;
    }

    /**
     * Calculate available profit to withdraw.
     */
    public function getAvailableProfit(): string
    {
        return bcsub($this->total_profit, $this->total_withdrawn, 8);
    }

    /**
     * Get investment progress percentage.
     */
    public function getProgressPercentage(): float
    {
        $totalDays = $this->start_date->diffInDays($this->end_date);
        $elapsedDays = $this->start_date->diffInDays(now());
        
        if ($totalDays <= 0) {
            return 100.0;
        }

        $progress = ($elapsedDays / $totalDays) * 100;
        return min(100.0, max(0.0, $progress));
    }

    /**
     * Get expected total return.
     */
    public function getExpectedTotalReturn(): string
    {
        return bcadd($this->amount, $this->plan->calculateTotalProfit($this->amount), 8);
    }

    /**
     * Get current ROI percentage.
     */
    public function getCurrentROI(): string
    {
        if (bccomp($this->amount, '0', 8) <= 0) {
            return '0.00';
        }

        $roi = bcdiv(bcmul($this->total_profit, '100', 8), $this->amount, 2);
        return $roi;
    }

    /**
     * Mark investment as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'end_date' => now()
        ]);
    }

    /**
     * Cancel investment.
     */
    public function cancel(string $reason = null): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['cancellation_reason'] = $reason;
        $metadata['cancelled_at'] = now()->toISOString();

        $this->update([
            'status' => 'cancelled',
            'metadata' => $metadata
        ]);
    }

    /**
     * Scope for active investments.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for investments due for profit distribution.
     */
    public function scopeDueForProfit($query)
    {
        return $query->where('status', 'active')
                    ->where('end_date', '>', now())
                    ->where(function ($q) {
                        $q->whereNull('last_profit_date')
                          ->orWhere('last_profit_date', '<', now()->subDay());
                    });
    }

    /**
     * Scope for matured investments.
     */
    public function scopeMatured($query)
    {
        return $query->where('status', 'active')
                    ->where('end_date', '<=', now());
    }
}