<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceHistory extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'price_history';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'cryptocurrency_symbol',
        'open_price',
        'high_price',
        'low_price',
        'close_price',
        'volume',
        'timestamp',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'open_price' => 'decimal:8',
            'high_price' => 'decimal:8',
            'low_price' => 'decimal:8',
            'close_price' => 'decimal:8',
            'volume' => 'integer',
            'timestamp' => 'datetime',
        ];
    }

    /**
     * Get the cryptocurrency for this price history.
     */
    public function cryptocurrency(): BelongsTo
    {
        return $this->belongsTo(Cryptocurrency::class, 'cryptocurrency_symbol', 'symbol');
    }

    /**
     * Get the price change from open to close.
     */
    public function getPriceChangeAttribute(): string
    {
        return bcsub($this->close_price, $this->open_price, 8);
    }

    /**
     * Get the price change percentage.
     */
    public function getPriceChangePercentAttribute(): float
    {
        if (bccomp($this->open_price, '0', 8) === 0) {
            return 0.0;
        }

        $change = bcsub($this->close_price, $this->open_price, 8);
        $percent = bcdiv($change, $this->open_price, 10);
        return (float) bcmul($percent, '100', 2);
    }

    /**
     * Check if this is a green candle (close > open).
     */
    public function isGreen(): bool
    {
        return bccomp($this->close_price, $this->open_price, 8) > 0;
    }

    /**
     * Check if this is a red candle (close < open).
     */
    public function isRed(): bool
    {
        return bccomp($this->close_price, $this->open_price, 8) < 0;
    }

    /**
     * Scope a query for a specific cryptocurrency.
     */
    public function scopeForCryptocurrency($query, string $symbol)
    {
        return $query->where('cryptocurrency_symbol', $symbol);
    }

    /**
     * Scope a query for a specific time range.
     */
    public function scopeInTimeRange($query, $startTime, $endTime)
    {
        return $query->whereBetween('timestamp', [$startTime, $endTime]);
    }

    /**
     * Scope a query for recent price history.
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('timestamp', '>=', now()->subHours($hours));
    }

    /**
     * Scope a query ordered by timestamp.
     */
    public function scopeOrderedByTime($query, string $direction = 'asc')
    {
        return $query->orderBy('timestamp', $direction);
    }
}
