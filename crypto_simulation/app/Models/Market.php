<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Market extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'base_coin',
        'quote_coin',
        'price',
        'volume_24h',
        'change_24h',
        'high_24h',
        'low_24h',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:8',
            'volume_24h' => 'decimal:2',
            'change_24h' => 'decimal:4',
            'high_24h' => 'decimal:8',
            'low_24h' => 'decimal:8',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the base cryptocurrency.
     */
    public function baseCryptocurrency(): BelongsTo
    {
        return $this->belongsTo(Cryptocurrency::class, 'base_coin', 'symbol');
    }

    /**
     * Get the quote cryptocurrency.
     */
    public function quoteCryptocurrency(): BelongsTo
    {
        return $this->belongsTo(Cryptocurrency::class, 'quote_coin', 'symbol');
    }
    /**
     * Get the trades for this market.
     */
    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class, 'cryptocurrency_symbol', 'base_coin');
    }

    /**
     * Get the orders for this market.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'cryptocurrency_symbol', 'base_coin');
    }

    /**
     * Get the market pair symbol (e.g., BTC/USDT).
     */
    public function getPairAttribute(): string
    {
        return $this->base_coin . '/' . $this->quote_coin;
    }

    /**
     * Scope a query to only include active markets.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Calculate 24h statistics for this market.
     */
    public function calculate24hStats(): array
    {
        $trades = $this->trades()
            ->where('executed_at', '>=', now()->subDay())
            ->orderBy('executed_at', 'desc')
            ->get();

        if ($trades->isEmpty()) {
            return [
                'volume_24h' => '0.00',
                'change_24h' => '0.0000',
                'high_24h' => $this->price,
                'low_24h' => $this->price,
            ];
        }

        $volume = $trades->sum(function ($trade) {
            return bcmul($trade->quantity, $trade->price, 8);
        });

        $prices = $trades->pluck('price')->map(fn($p) => (float)$p);
        $high = $prices->max();
        $low = $prices->min();

        $oldestTrade = $trades->last();
        $latestTrade = $trades->first();
        
        $change = $oldestTrade && $latestTrade ? 
            (((float)$latestTrade->price - (float)$oldestTrade->price) / (float)$oldestTrade->price) * 100 : 0;

        return [
            'volume_24h' => number_format($volume, 2),
            'change_24h' => number_format($change, 4),
            'high_24h' => number_format($high, 8),
            'low_24h' => number_format($low, 8),
        ];
    }
}