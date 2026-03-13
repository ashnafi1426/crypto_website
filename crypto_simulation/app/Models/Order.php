<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'cryptocurrency_symbol',
        'order_type',
        'side',
        'quantity',
        'price',
        'filled_quantity',
        'status',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:8',
            'price' => 'decimal:8',
            'filled_quantity' => 'decimal:8',
        ];
    }

    /**
     * Get the user that owns the order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the cryptocurrency for this order.
     */
    public function cryptocurrency(): BelongsTo
    {
        return $this->belongsTo(Cryptocurrency::class, 'cryptocurrency_symbol', 'symbol');
    }

    /**
     * Get the trades where this order was the buy order.
     */
    public function buyTrades(): HasMany
    {
        return $this->hasMany(Trade::class, 'buy_order_id');
    }

    /**
     * Get the trades where this order was the sell order.
     */
    public function sellTrades(): HasMany
    {
        return $this->hasMany(Trade::class, 'sell_order_id');
    }

    /**
     * Get the remaining quantity to be filled.
     */
    public function getRemainingQuantityAttribute(): string
    {
        return bcsub($this->quantity, $this->filled_quantity, 8);
    }

    /**
     * Check if the order is completely filled.
     */
    public function isFilled(): bool
    {
        return bccomp($this->quantity, $this->filled_quantity, 8) === 0;
    }

    /**
     * Check if the order is partially filled.
     */
    public function isPartiallyFilled(): bool
    {
        return bccomp($this->filled_quantity, '0', 8) > 0 && !$this->isFilled();
    }

    /**
     * Update the filled quantity.
     */
    public function updateFilledQuantity(string $quantity): void
    {
        $newFilledQuantity = bcadd($this->filled_quantity, $quantity, 8);
        
        if (bccomp($newFilledQuantity, $this->quantity, 8) > 0) {
            throw new \InvalidArgumentException('Filled quantity cannot exceed order quantity');
        }

        $this->update(['filled_quantity' => $newFilledQuantity]);

        // Update status based on filled quantity
        if ($this->isFilled()) {
            $this->update(['status' => 'filled']);
        } elseif ($this->isPartiallyFilled()) {
            $this->update(['status' => 'partial']);
        }
    }

    /**
     * Cancel the order.
     */
    public function cancel(): void
    {
        if ($this->status === 'filled') {
            throw new \InvalidArgumentException('Cannot cancel a filled order');
        }

        $this->update(['status' => 'cancelled']);
    }

    /**
     * Scope a query to only include active orders.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'partial']);
    }

    /**
     * Scope a query to only include buy orders.
     */
    public function scopeBuy($query)
    {
        return $query->where('side', 'buy');
    }

    /**
     * Scope a query to only include sell orders.
     */
    public function scopeSell($query)
    {
        return $query->where('side', 'sell');
    }

    /**
     * Scope a query for a specific cryptocurrency.
     */
    public function scopeForCryptocurrency($query, string $symbol)
    {
        return $query->where('cryptocurrency_symbol', $symbol);
    }
}
