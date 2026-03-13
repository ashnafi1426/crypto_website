<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferralProgram extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'referral_code',
        'commission_rate',
        'total_referrals',
        'active_referrals',
        'total_earned',
        'pending_payout',
        'status'
    ];

    protected $casts = [
        'commission_rate' => 'decimal:4',
        'total_earned' => 'decimal:8',
        'pending_payout' => 'decimal:8'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by');
    }
}