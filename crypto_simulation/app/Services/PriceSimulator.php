<?php

namespace App\Services;

use App\Models\Cryptocurrency;
use App\Models\PriceHistory;
use App\Services\Contracts\PriceSimulatorInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class PriceSimulator implements PriceSimulatorInterface
{
    /**
     * Update prices for all active cryptocurrencies.
     */
    public function updatePrices(): array
    {
        $updatedCount = 0;
        $volatilityAlerts = [];
        
        DB::transaction(function () use (&$updatedCount, &$volatilityAlerts) {
            $cryptocurrencies = Cryptocurrency::where('is_active', true)->get();
            
            foreach ($cryptocurrencies as $crypto) {
                $newPrice = $this->generateNewPrice($crypto);
                $volume = $this->generateVolume($crypto, $newPrice);
                
                // Update current price
                $oldPrice = $crypto->current_price;
                $crypto->updatePrice($newPrice);
                
                // Create price history record
                $this->createPriceHistoryRecord($crypto, $newPrice, $volume);
                
                // Check for volatility alerts
                $alert = $this->checkVolatilityAlert($crypto->symbol, $oldPrice, $newPrice);
                if ($alert) {
                    $volatilityAlerts[] = $alert;
                }
                
                $updatedCount++;
                
                // Clear cached price data
                Cache::forget("price:{$crypto->symbol}");
                Cache::forget("price_history:{$crypto->symbol}");
            }
        });
        
        // Log volatility alerts
        foreach ($volatilityAlerts as $alert) {
            Log::warning('Volatility alert triggered', $alert);
        }
        
        return [
            'updated_count' => $updatedCount,
            'volatility_alerts' => count($volatilityAlerts),
            'alerts' => $volatilityAlerts
        ];
    }

    /**
     * Get current price for cryptocurrency.
     */
    public function getCurrentPrice(string $cryptocurrency): array
    {
        $cacheKey = "price:{$cryptocurrency}";
        
        return Cache::remember($cacheKey, 30, function () use ($cryptocurrency) {
            $crypto = Cryptocurrency::where('symbol', $cryptocurrency)->first();
            
            if (!$crypto) {
                return ['error' => 'Cryptocurrency not found'];
            }
            
            $change24h = $crypto->getPriceChange24h();
            
            return [
                'symbol' => $crypto->symbol,
                'name' => $crypto->name,
                'price' => $crypto->current_price,
                'change_24h' => $change24h,
                'volatility' => $crypto->volatility,
                'is_active' => $crypto->is_active,
                'updated_at' => $crypto->updated_at->toISOString()
            ];
        });
    }

    /**
     * Get price history for cryptocurrency.
     */
    public function getPriceHistory(string $cryptocurrency, array $dateRange): array
    {
        $startDate = Carbon::parse($dateRange['start'] ?? now()->subDays(30));
        $endDate = Carbon::parse($dateRange['end'] ?? now());
        
        $cacheKey = "price_history:{$cryptocurrency}:" . $startDate->format('Y-m-d') . ':' . $endDate->format('Y-m-d');
        
        return Cache::remember($cacheKey, 3600, function () use ($cryptocurrency, $startDate, $endDate) {
            $history = PriceHistory::forCryptocurrency($cryptocurrency)
                ->inTimeRange($startDate, $endDate)
                ->orderedByTime('asc')
                ->get();
            
            return [
                'symbol' => $cryptocurrency,
                'start_date' => $startDate->toISOString(),
                'end_date' => $endDate->toISOString(),
                'count' => $history->count(),
                'history' => $history->map(function ($record) {
                    return [
                        'timestamp' => $record->timestamp->toISOString(),
                        'open' => $record->open_price,
                        'high' => $record->high_price,
                        'low' => $record->low_price,
                        'close' => $record->close_price,
                        'volume' => $record->volume,
                        'change' => $record->price_change,
                        'change_percent' => $record->price_change_percent
                    ];
                })
            ];
        });
    }

    /**
     * Set volatility for cryptocurrency.
     */
    public function setCryptocurrencyVolatility(string $cryptocurrency, float $volatility): bool
    {
        if ($volatility < 0 || $volatility > 1) {
            return false;
        }
        
        $crypto = Cryptocurrency::where('symbol', $cryptocurrency)->first();
        
        if (!$crypto) {
            return false;
        }
        
        $crypto->update(['volatility' => $volatility]);
        
        Log::info('Cryptocurrency volatility updated', [
            'symbol' => $cryptocurrency,
            'old_volatility' => $crypto->getOriginal('volatility'),
            'new_volatility' => $volatility
        ]);
        
        return true;
    }

    /**
     * Generate candlestick data.
     */
    public function generateCandlestickData(string $cryptocurrency, string $interval): array
    {
        $intervals = [
            '1m' => 1,
            '5m' => 5,
            '15m' => 15,
            '30m' => 30,
            '1h' => 60,
            '4h' => 240,
            '1d' => 1440
        ];
        
        if (!isset($intervals[$interval])) {
            return ['error' => 'Invalid interval'];
        }
        
        $minutes = $intervals[$interval];
        $startTime = now()->subDays(30); // Last 30 days
        
        $cacheKey = "candlestick:{$cryptocurrency}:{$interval}";
        
        return Cache::remember($cacheKey, 300, function () use ($cryptocurrency, $minutes, $startTime) {
            // For intervals less than 1 day, aggregate from price history
            if ($minutes < 1440) {
                return $this->aggregateCandlestickData($cryptocurrency, $minutes, $startTime);
            }
            
            // For daily intervals, use existing price history records
            $history = PriceHistory::forCryptocurrency($cryptocurrency)
                ->where('timestamp', '>=', $startTime)
                ->orderedByTime('asc')
                ->get();
            
            return [
                'symbol' => $cryptocurrency,
                'interval' => $minutes . 'm',
                'candlesticks' => $history->map(function ($record) {
                    return [
                        'timestamp' => $record->timestamp->toISOString(),
                        'open' => $record->open_price,
                        'high' => $record->high_price,
                        'low' => $record->low_price,
                        'close' => $record->close_price,
                        'volume' => $record->volume
                    ];
                })
            ];
        });
    }

    /**
     * Generate new price based on volatility.
     */
    private function generateNewPrice(Cryptocurrency $crypto): string
    {
        $currentPrice = (float) $crypto->current_price;
        $volatility = $crypto->volatility;
        
        // Generate random price movement using normal distribution
        // Box-Muller transformation for normal distribution
        $u1 = mt_rand() / mt_getrandmax();
        $u2 = mt_rand() / mt_getrandmax();
        $z0 = sqrt(-2 * log($u1)) * cos(2 * pi() * $u2);
        
        // Scale by volatility (daily volatility converted to 30-second intervals)
        $intervalVolatility = $volatility / sqrt(24 * 60 * 2); // 30-second intervals per day
        $priceChange = $currentPrice * $intervalVolatility * $z0;
        
        $newPrice = $currentPrice + $priceChange;
        
        // Ensure price doesn't go negative
        $newPrice = max($newPrice, $currentPrice * 0.01); // Minimum 1% of current price
        
        return number_format($newPrice, 8, '.', '');
    }

    /**
     * Generate volume correlated with price movement.
     */
    private function generateVolume(Cryptocurrency $crypto, string $newPrice): int
    {
        $currentPrice = (float) $crypto->current_price;
        $priceChange = abs(((float) $newPrice - $currentPrice) / $currentPrice);
        
        // Base volume (random between 1000 and 10000)
        $baseVolume = mt_rand(1000, 10000);
        
        // Increase volume with higher price volatility
        $volumeMultiplier = 1 + ($priceChange * 10); // Higher volatility = higher volume
        
        return (int) ($baseVolume * $volumeMultiplier);
    }

    /**
     * Create price history record.
     */
    private function createPriceHistoryRecord(Cryptocurrency $crypto, string $newPrice, int $volume): void
    {
        $currentTime = now();
        $currentPrice = (float) $crypto->current_price;
        $price = (float) $newPrice;
        
        // For simplicity, we'll create a single price point
        // In a real system, you'd aggregate multiple price points into OHLC data
        PriceHistory::create([
            'cryptocurrency_symbol' => $crypto->symbol,
            'open_price' => $currentPrice,
            'high_price' => max($currentPrice, $price),
            'low_price' => min($currentPrice, $price),
            'close_price' => $price,
            'volume' => $volume,
            'timestamp' => $currentTime
        ]);
        
        // Clean up old price history (keep only 365+ days)
        $cutoffDate = now()->subDays(366);
        PriceHistory::where('cryptocurrency_symbol', $crypto->symbol)
            ->where('timestamp', '<', $cutoffDate)
            ->delete();
    }

    /**
     * Check for volatility alerts (>10% in 1 hour).
     */
    private function checkVolatilityAlert(string $symbol, string $oldPrice, string $newPrice): ?array
    {
        $oneHourAgo = now()->subHour();
        
        // Get price from 1 hour ago
        $hourAgoPrice = PriceHistory::where('cryptocurrency_symbol', $symbol)
            ->where('timestamp', '>=', $oneHourAgo)
            ->orderBy('timestamp', 'asc')
            ->first();
        
        if (!$hourAgoPrice) {
            return null;
        }
        
        $oldPriceFloat = (float) $hourAgoPrice->close_price;
        $newPriceFloat = (float) $newPrice;
        
        $changePercent = abs(($newPriceFloat - $oldPriceFloat) / $oldPriceFloat) * 100;
        
        if ($changePercent > 10) {
            return [
                'symbol' => $symbol,
                'change_percent' => round($changePercent, 2),
                'old_price' => $hourAgoPrice->close_price,
                'new_price' => $newPrice,
                'timestamp' => now()->toISOString(),
                'direction' => $newPriceFloat > $oldPriceFloat ? 'up' : 'down'
            ];
        }
        
        return null;
    }

    /**
     * Aggregate candlestick data for smaller intervals.
     */
    private function aggregateCandlestickData(string $cryptocurrency, int $intervalMinutes, Carbon $startTime): array
    {
        // This is a simplified implementation
        // In a real system, you'd have more granular price data to aggregate
        $history = PriceHistory::forCryptocurrency($cryptocurrency)
            ->where('timestamp', '>=', $startTime)
            ->orderedByTime('asc')
            ->get();
        
        $candlesticks = [];
        $intervalSeconds = $intervalMinutes * 60;
        
        foreach ($history as $record) {
            // Round timestamp to interval boundary
            $intervalStart = $record->timestamp->copy()->startOfMinute();
            $intervalStart->minute = (int) (floor($intervalStart->minute / $intervalMinutes) * $intervalMinutes);
            
            $key = $intervalStart->toISOString();
            
            if (!isset($candlesticks[$key])) {
                $candlesticks[$key] = [
                    'timestamp' => $key,
                    'open' => $record->open_price,
                    'high' => $record->high_price,
                    'low' => $record->low_price,
                    'close' => $record->close_price,
                    'volume' => $record->volume
                ];
            } else {
                // Update high, low, close, and volume
                $candlesticks[$key]['high'] = bccomp($record->high_price, $candlesticks[$key]['high'], 8) > 0 
                    ? $record->high_price : $candlesticks[$key]['high'];
                $candlesticks[$key]['low'] = bccomp($record->low_price, $candlesticks[$key]['low'], 8) < 0 
                    ? $record->low_price : $candlesticks[$key]['low'];
                $candlesticks[$key]['close'] = $record->close_price;
                $candlesticks[$key]['volume'] += $record->volume;
            }
        }
        
        return [
            'symbol' => $cryptocurrency,
            'interval' => $intervalMinutes . 'm',
            'candlesticks' => array_values($candlesticks)
        ];
    }
}