<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trade extends Model
{
    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'buy_order_id',
        'sell_order_id',
        'cryptocurrency_symbol',
        'quantity',
        'price',
        'executed_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:8',
            'price' => 'decimal:8',
            'executed_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Automatically set executed_at when creating
        static::creating(function ($model) {
            if (!$model->executed_at) {
                $model->executed_at = now();
            }
        });
    }

    /**
     * Get the buy order for this trade.
     */
    public function buyOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'buy_order_id');
    }

    /**
     * Get the sell order for this trade.
     */
    public function sellOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'sell_order_id');
    }

    /**
     * Get the cryptocurrency for this trade.
     */
    public function cryptocurrency(): BelongsTo
    {
        return $this->belongsTo(Cryptocurrency::class, 'cryptocurrency_symbol', 'symbol');
    }

    /**
     * Get the buyer (user who placed the buy order).
     */
    public function getBuyerAttribute()
    {
        return $this->buyOrder->user ?? null;
    }

    /**
     * Get the seller (user who placed the sell order).
     */
    public function getSellerAttribute()
    {
        return $this->sellOrder->user ?? null;
    }

    /**
     * Get the total trade value.
     */
    public function getTotalValueAttribute(): string
    {
        return bcmul($this->quantity, $this->price, 8);
    }

    /**
     * Scope a query for a specific cryptocurrency.
     */
    public function scopeForCryptocurrency($query, string $symbol)
    {
        return $query->where('cryptocurrency_symbol', $symbol);
    }

    /**
     * Scope a query for trades within a date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('executed_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query for recent trades.
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('executed_at', '>=', now()->subHours($hours));
    }
}
