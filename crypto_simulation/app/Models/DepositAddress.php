<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepositAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'currency',
        'network',
        'address',
        'type',
        'is_active',
        'last_used_at',
        'metadata'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'metadata' => 'array'
    ];

    /**
     * Get the user that owns the deposit address
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get active addresses
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get addresses by currency
     */
    public function scopeByCurrency($query, $currency)
    {
        return $query->where('currency', strtoupper($currency));
    }

    /**
     * Scope to get addresses by network
     */
    public function scopeByNetwork($query, $network)
    {
        return $query->where('network', $network);
    }

    /**
     * Get formatted address for display
     */
    public function getFormattedAddressAttribute()
    {
        if (strlen($this->address) > 20) {
            return substr($this->address, 0, 10) . '...' . substr($this->address, -8);
        }
        return $this->address;
    }

    /**
     * Update last used timestamp
     */
    public function markAsUsed()
    {
        $this->update(['last_used_at' => now()]);
    }
}
