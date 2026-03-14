<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentDistribution extends Model
{
    use HasFactory;

    protected $fillable = [
        'investment_id',
        'user_id',
        'amount',
        'type',
        'distribution_date',
        'status',
        'reference_id',
        'notes'
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'distribution_date' => 'date'
    ];

    /**
     * Get the investment that owns the distribution.
     */
    public function investment(): BelongsTo
    {
        return $this->belongsTo(UserInvestment::class, 'investment_id');
    }

    /**
     * Get the user that owns the distribution.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if distribution is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Mark distribution as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update(['status' => 'completed']);
    }

    /**
     * Mark distribution as failed.
     */
    public function markAsFailed(string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'notes' => $reason
        ]);
    }

    /**
     * Scope for completed distributions.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for pending distributions.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for specific distribution type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}