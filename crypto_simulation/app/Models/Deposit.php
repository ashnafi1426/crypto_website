<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deposit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'currency',
        'type',
        'amount',
        'fee',
        'net_amount',
        'wallet_address',
        'txid',
        'confirmations',
        'required_confirmations',
        'payment_method',
        'payment_reference',
        'payment_details',
        'status',
        'confirmed_at',
        'completed_at',
        'notes',
        'processed_by',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'fee' => 'decimal:8',
        'net_amount' => 'decimal:8',
        'payment_details' => 'array',
        'metadata' => 'array',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirming($query)
    {
        return $query->where('status', 'confirming');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCrypto($query)
    {
        return $query->where('type', 'crypto');
    }

    public function scopeFiat($query)
    {
        return $query->where('type', 'fiat');
    }

    // Accessors
    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed';
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending';
    }

    public function getIsConfirmingAttribute(): bool
    {
        return $this->status === 'confirming';
    }

    public function getConfirmationProgressAttribute(): float
    {
        if ($this->required_confirmations <= 0) {
            return 100;
        }
        
        return min(100, ($this->confirmations / $this->required_confirmations) * 100);
    }

    // Methods
    public function markAsConfirming(): void
    {
        $this->update([
            'status' => 'confirming',
            'confirmed_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(?string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'notes' => $reason,
        ]);
    }

    public function updateConfirmations(int $confirmations): void
    {
        $this->update(['confirmations' => $confirmations]);
        
        if ($confirmations >= $this->required_confirmations && $this->status === 'confirming') {
            $this->markAsCompleted();
        }
    }
}