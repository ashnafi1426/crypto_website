<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cryptocurrency extends Model
{
    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'symbol';

    /**
     * The "type" of the primary key ID.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'symbol',
        'name',
        'current_price',
        'market_cap',
        'volume_24h',
        'price_change_24h',
        'price_change_percentage_24h',
        'circulating_supply',
        'logo_url',
        'volatility',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'current_price' => 'decimal:8',
            'market_cap' => 'decimal:2',
            'volume_24h' => 'decimal:2',
            'price_change_24h' => 'decimal:8',
            'price_change_percentage_24h' => 'decimal:4',
            'circulating_supply' => 'decimal:2',
            'volatility' => 'float',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the wallets for this cryptocurrency.
     */
    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class, 'cryptocurrency_symbol', 'symbol');
    }

    /**
     * Get the orders for this cryptocurrency.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'cryptocurrency_symbol', 'symbol');
    }

    /**
     * Get the price history for this cryptocurrency.
     */
    public function priceHistory(): HasMany
    {
        return $this->hasMany(PriceHistory::class, 'cryptocurrency_symbol', 'symbol');
    }

    /**
     * Get the transaction records for this cryptocurrency.
     */
    public function transactionRecords(): HasMany
    {
        return $this->hasMany(TransactionRecord::class, 'cryptocurrency_symbol', 'symbol');
    }

    /**
     * Scope a query to only include active cryptocurrencies.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Update the current price for this cryptocurrency.
     */
    public function updatePrice(string $newPrice): void
    {
        $this->update(['current_price' => $newPrice]);
    }

    /**
     * Get the 24-hour price change percentage.
     */
    public function getPriceChange24h(): float
    {
        $yesterday = $this->priceHistory()
            ->where('timestamp', '>=', now()->subDay())
            ->orderBy('timestamp', 'asc')
            ->first();

        if (!$yesterday) {
            return 0.0;
        }

        $oldPrice = (float) $yesterday->close_price;
        $currentPrice = (float) $this->current_price;

        return $oldPrice > 0 ? (($currentPrice - $oldPrice) / $oldPrice) * 100 : 0.0;
    }
}
