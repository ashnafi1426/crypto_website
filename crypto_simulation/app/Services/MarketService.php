<?php

namespace App\Services;

use App\Models\Cryptocurrency;
use App\Models\Market;
use App\Models\Trade;
use App\Models\PriceHistory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MarketService
{
    /**
     * Get all markets with current statistics.
     */
    public function getAllMarkets(): array
    {
        return Cache::remember('markets_data', 300, function () {
            $cryptocurrencies = Cryptocurrency::active()
                ->orderBy('market_cap', 'desc')
                ->get();

            return $cryptocurrencies->map(function ($crypto) {
                $stats = $this->calculate24hStats($crypto->symbol);
                
                return [
                    'pair' => $crypto->symbol . '/USDT',
                    'symbol' => $crypto->symbol,
                    'name' => $crypto->name,
                    'price' => $crypto->current_price,
                    'market_cap' => $crypto->market_cap ?: $this->calculateMarketCap($crypto),
                    'volume_24h' => $stats['volume_24h'],
                    'change_24h' => $stats['change_24h'],
                    'change_percentage_24h' => $stats['change_percentage_24h'],
                    'high_24h' => $stats['high_24h'],
                    'low_24h' => $stats['low_24h'],
                    'logo_url' => $crypto->logo_url,
                    'last_updated' => $crypto->updated_at->toISOString(),
                ];
            })->toArray();
        });
    }

    /**
     * Calculate 24-hour statistics for a cryptocurrency.
     */
    public function calculate24hStats(string $symbol): array
    {
        $cacheKey = "24h_stats_{$symbol}";
        
        return Cache::remember($cacheKey, 300, function () use ($symbol) {
            // Get trades from last 24 hours
            $trades = Trade::where('cryptocurrency_symbol', $symbol)
                ->where('executed_at', '>=', now()->subDay())
                ->orderBy('executed_at', 'desc')
                ->get();

            if ($trades->isEmpty()) {
                $currentPrice = Cryptocurrency::where('symbol', $symbol)->value('current_price') ?: '0.00000000';
                return [
                    'volume_24h' => '0.00',
                    'change_24h' => '0.00000000',
                    'change_percentage_24h' => '0.0000',
                    'high_24h' => $currentPrice,
                    'low_24h' => $currentPrice,
                ];
            }

            // Calculate volume (sum of all trade amounts)
            $volume = $trades->sum(function ($trade) {
                return bcmul($trade->quantity, $trade->price, 8);
            });

            // Get price range
            $prices = $trades->pluck('price')->map(fn($p) => (float)$p);
            $high = $prices->max();
            $low = $prices->min();

            // Calculate price change
            $oldestTrade = $trades->last();
            $latestTrade = $trades->first();
            
            $priceChange = '0.00000000';
            $priceChangePercentage = '0.0000';
            
            if ($oldestTrade && $latestTrade) {
                $priceChange = bcsub($latestTrade->price, $oldestTrade->price, 8);
                if (bccomp($oldestTrade->price, '0', 8) > 0) {
                    $priceChangePercentage = bcmul(
                        bcdiv($priceChange, $oldestTrade->price, 10),
                        '100',
                        4
                    );
                }
            }

            return [
                'volume_24h' => number_format($volume, 2),
                'change_24h' => $priceChange,
                'change_percentage_24h' => $priceChangePercentage,
                'high_24h' => number_format($high, 8),
                'low_24h' => number_format($low, 8),
            ];
        });
    }
    /**
     * Calculate market cap for a cryptocurrency.
     */
    public function calculateMarketCap(Cryptocurrency $crypto): string
    {
        if (!$crypto->circulating_supply) {
            return '0.00';
        }

        return bcmul($crypto->current_price, $crypto->circulating_supply, 2);
    }

    /**
     * Get price history for a cryptocurrency.
     */
    public function getPriceHistory(string $symbol, int $days = 7, string $interval = '1h'): array
    {
        $cacheKey = "price_history_{$symbol}_{$days}_{$interval}";
        
        return Cache::remember($cacheKey, 300, function () use ($symbol, $days, $interval) {
            $query = PriceHistory::where('cryptocurrency_symbol', $symbol)
                ->where('timestamp', '>=', now()->subDays($days))
                ->orderBy('timestamp', 'asc');

            // Apply interval filtering
            if ($interval === '4h') {
                $query->whereRaw('strftime("%H", timestamp) % 4 = 0');
            } elseif ($interval === '1d') {
                $query->whereRaw('strftime("%H", timestamp) = "00"');
            }

            return $query->get()->map(function ($record) {
                return [
                    'timestamp' => $record->timestamp->timestamp * 1000,
                    'open' => $record->open_price,
                    'high' => $record->high_price,
                    'low' => $record->low_price,
                    'close' => $record->close_price,
                    'volume' => $record->volume,
                ];
            })->toArray();
        });
    }

    /**
     * Get market statistics for dashboard.
     */
    public function getMarketStatistics(): array
    {
        return Cache::remember('market_statistics', 300, function () {
            $totalMarketCap = Cryptocurrency::active()->sum('market_cap');
            $totalVolume = $this->getTotalVolume24h();
            $btcDominance = $this->getBtcDominance();
            
            return [
                'total_market_cap' => $totalMarketCap,
                'total_volume_24h' => $totalVolume,
                'btc_dominance' => $btcDominance,
                'active_cryptocurrencies' => Cryptocurrency::active()->count(),
            ];
        });
    }

    /**
     * Get total 24h volume across all cryptocurrencies.
     */
    private function getTotalVolume24h(): string
    {
        $trades = Trade::where('executed_at', '>=', now()->subDay())->get();
        
        $totalVolume = $trades->sum(function ($trade) {
            return bcmul($trade->quantity, $trade->price, 8);
        });

        return number_format($totalVolume, 2);
    }

    /**
     * Get Bitcoin dominance percentage.
     */
    private function getBtcDominance(): string
    {
        $btcMarketCap = Cryptocurrency::where('symbol', 'BTC')->value('market_cap') ?: '0';
        $totalMarketCap = Cryptocurrency::active()->sum('market_cap');

        if (bccomp($totalMarketCap, '0', 2) === 0) {
            return '0.00';
        }

        $dominance = bcmul(
            bcdiv($btcMarketCap, $totalMarketCap, 10),
            '100',
            2
        );

        return $dominance;
    }

    /**
     * Update market data for all cryptocurrencies.
     */
    public function updateMarketData(): void
    {
        $cryptocurrencies = Cryptocurrency::active()->get();

        foreach ($cryptocurrencies as $crypto) {
            $stats = $this->calculate24hStats($crypto->symbol);
            
            $crypto->update([
                'volume_24h' => str_replace(',', '', $stats['volume_24h']),
                'price_change_24h' => $stats['change_24h'],
                'price_change_percentage_24h' => $stats['change_percentage_24h'],
                'market_cap' => $this->calculateMarketCap($crypto),
            ]);
        }

        // Clear cache after update
        Cache::forget('markets_data');
        Cache::forget('market_statistics');
    }
}