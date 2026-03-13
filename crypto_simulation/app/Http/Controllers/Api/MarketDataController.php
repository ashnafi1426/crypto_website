<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cryptocurrency;
use App\Models\PriceHistory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class MarketDataController extends Controller
{
    /**
     * Get all cryptocurrencies with current prices.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $cryptocurrencies = Cache::remember('cryptocurrencies_list', 300, function () {
                return Cryptocurrency::select([
                    'symbol',
                    'name',
                    'current_price',
                    'market_cap',
                    'volume_24h',
                    'price_change_24h',
                    'price_change_percentage_24h',
                    'updated_at'
                ])
                ->orderBy('market_cap', 'desc')
                ->get()
                ->map(function ($crypto) {
                    return [
                        'symbol' => $crypto->symbol,
                        'name' => $crypto->name,
                        'current_price' => $crypto->current_price,
                        'market_cap' => $crypto->market_cap,
                        'volume_24h' => $crypto->volume_24h,
                        'price_change_24h' => $crypto->price_change_24h,
                        'price_change_percentage_24h' => $crypto->price_change_percentage_24h,
                        'last_updated' => $crypto->updated_at->toISOString()
                    ];
                });
            });

            return response()->json([
                'success' => true,
                'cryptocurrencies' => $cryptocurrencies,
                'count' => $cryptocurrencies->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve market data'
            ], 500);
        }
    }

    /**
     * Get price history for a cryptocurrency.
     */
    public function priceHistory(Request $request, string $symbol): JsonResponse
    {
        try {
            $symbol = strtoupper($symbol);
            $days = $request->get('days', 7);
            $interval = $request->get('interval', '1h');

            // Validate parameters
            if (!in_array($days, [1, 7, 30, 90, 365])) {
                $days = 7;
            }

            if (!in_array($interval, ['1h', '4h', '1d'])) {
                $interval = '1h';
            }

            $cacheKey = "price_history_{$symbol}_{$days}_{$interval}";
            
            $priceHistory = Cache::remember($cacheKey, 300, function () use ($symbol, $days, $interval) {
                $query = PriceHistory::where('cryptocurrency_symbol', $symbol)
                    ->where('created_at', '>=', now()->subDays($days))
                    ->orderBy('created_at', 'asc');

                // Apply interval filtering
                if ($interval === '4h') {
                    $query->whereRaw('EXTRACT(HOUR FROM created_at) % 4 = 0');
                } elseif ($interval === '1d') {
                    $query->whereRaw('EXTRACT(HOUR FROM created_at) = 0');
                }

                return $query->get()->map(function ($record) {
                    return [
                        'timestamp' => $record->created_at->timestamp * 1000, // JavaScript timestamp
                        'price' => $record->price,
                        'volume' => $record->volume ?? '0',
                        'market_cap' => $record->market_cap ?? '0'
                    ];
                });
            });

            return response()->json([
                'success' => true,
                'symbol' => $symbol,
                'days' => $days,
                'interval' => $interval,
                'data' => $priceHistory
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve price history'
            ], 500);
        }
    }

    /**
     * Get candlestick data for a cryptocurrency.
     */
    public function candlestick(Request $request, string $symbol): JsonResponse
    {
        try {
            $symbol = strtoupper($symbol);
            $days = $request->get('days', 1);
            $interval = $request->get('interval', '1h');

            // Validate parameters
            if (!in_array($days, [1, 7, 30])) {
                $days = 1;
            }

            if (!in_array($interval, ['15m', '1h', '4h', '1d'])) {
                $interval = '1h';
            }

            $cacheKey = "candlestick_{$symbol}_{$days}_{$interval}";

            $candlestickData = Cache::remember($cacheKey, 300, function () use ($symbol, $days, $interval) {
                // For simplicity, generate mock candlestick data based on price history
                $basePrice = Cryptocurrency::where('symbol', $symbol)->value('current_price') ?? '1000.00';
                $data = [];
                
                $intervalMinutes = match($interval) {
                    '15m' => 15,
                    '1h' => 60,
                    '4h' => 240,
                    '1d' => 1440,
                    default => 60
                };

                $totalIntervals = ($days * 24 * 60) / $intervalMinutes;
                $startTime = now()->subDays($days);

                for ($i = 0; $i < $totalIntervals; $i++) {
                    $timestamp = $startTime->copy()->addMinutes($i * $intervalMinutes);
                    
                    // Generate realistic OHLCV data
                    $open = bcmul($basePrice, (1 + (mt_rand(-200, 200) / 10000)), 8);
                    $volatility = mt_rand(50, 300) / 10000;
                    
                    $high = bcmul($open, (1 + $volatility), 8);
                    $low = bcmul($open, (1 - $volatility), 8);
                    $close = bcmul($open, (1 + (mt_rand(-100, 100) / 10000)), 8);
                    $volume = mt_rand(100000, 1000000);

                    $data[] = [
                        'timestamp' => $timestamp->timestamp * 1000,
                        'open' => $open,
                        'high' => $high,
                        'low' => $low,
                        'close' => $close,
                        'volume' => (string)$volume
                    ];

                    $basePrice = $close; // Use close as next open
                }

                return $data;
            });

            return response()->json([
                'success' => true,
                'symbol' => $symbol,
                'days' => $days,
                'interval' => $interval,
                'data' => $candlestickData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve candlestick data'
            ], 500);
        }
    }
}