<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cryptocurrency;
use App\Models\PriceHistory;
use App\Services\MarketService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class MarketDataController extends Controller
{
    protected MarketService $marketService;

    public function __construct(MarketService $marketService)
    {
        $this->marketService = $marketService;
    }
    /**
     * Get all cryptocurrencies with current prices and market data.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $markets = $this->marketService->getAllMarkets();

            return response()->json([
                'success' => true,
                'cryptocurrencies' => $markets,
                'count' => count($markets)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve market data',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get market statistics for dashboard.
     */
    public function statistics(): JsonResponse
    {
        try {
            $statistics = $this->marketService->getMarketStatistics();

            return response()->json([
                'success' => true,
                'statistics' => $statistics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve market statistics',
                'error' => config('app.debug') ? $e->getMessage() : null
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

            $priceHistory = $this->marketService->getPriceHistory($symbol, $days, $interval);

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
                'message' => 'Failed to retrieve price history',
                'error' => config('app.debug') ? $e->getMessage() : null
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