<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'currency',
        'type',
        'amount',
        'fee',
        'net_amount',
        'to_address',
        'txid',
        'confirmations',
        'network_fee',
        'payment_method',
        'payment_details',
        'payment_reference',
        'two_factor_verified',
        'email_verified',
        'verification_code',
        'verification_expires_at',
        'status',
        'verified_at',
        'approved_at',
        'processed_at',
        'completed_at',
        'approved_by',
        'processed_by',
        'admin_notes',
        'rejection_reason',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'fee' => 'decimal:8',
        'net_amount' => 'decimal:8',
        'network_fee' => 'decimal:8',
        'payment_details' => 'array',
        'metadata' => 'array',
        'two_factor_verified' => 'boolean',
        'email_verified' => 'boolean',
        'verification_expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
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

    public function scopeVerified($query)
    {
        return $query->where('status', 'verified');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
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

    public function getRequiresVerificationAttribute(): bool
    {
        return !$this->two_factor_verified || !$this->email_verified;
    }

    public function getCanBeApprovedAttribute(): bool
    {
        return $this->status === 'verified' && $this->two_factor_verified && $this->email_verified;
    }

    // Methods
    public function markAsVerified(): void
    {
        $this->update([
            'status' => 'verified',
            'verified_at' => now(),
        ]);
    }

    public function markAsApproved(User $admin): void
    {
        $this->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ]);
    }

    public function markAsProcessing(User $admin = null): void
    {
        $this->update([
            'status' => 'processing',
            'processed_at' => now(),
            'processed_by' => $admin?->id,
        ]);
    }

    public function markAsCompleted(string $txid = null): void
    {
        $updateData = [
            'status' => 'completed',
            'completed_at' => now(),
        ];

        if ($txid) {
            $updateData['txid'] = $txid;
        }

        $this->update($updateData);
    }

    public function markAsFailed(string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'admin_notes' => $reason,
        ]);
    }

    public function markAsRejected(string $reason, User $admin): void
    {
        $this->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);
    }

    public function generateVerificationCode(): string
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        $this->update([
            'verification_code' => $code,
            'verification_expires_at' => now()->addMinutes(15),
        ]);

        return $code;
    }

    public function verifyCode(string $code): bool
    {
        if ($this->verification_code !== $code) {
            return false;
        }

        if ($this->verification_expires_at && $this->verification_expires_at->isPast()) {
            return false;
        }

        $this->update([
            'email_verified' => true,
            'verification_code' => null,
            'verification_expires_at' => null,
        ]);

        return true;
    }
}