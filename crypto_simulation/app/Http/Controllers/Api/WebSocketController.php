<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MarketService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WebSocketController extends Controller
{
    protected MarketService $marketService;

    public function __construct(MarketService $marketService)
    {
        $this->marketService = $marketService;
    }

    /**
     * Get real-time market data for WebSocket streaming.
     */
    public function marketData(): JsonResponse
    {
        try {
            $markets = $this->marketService->getAllMarkets();
            $statistics = $this->marketService->getMarketStatistics();

            return response()->json([
                'type' => 'market_data',
                'timestamp' => now()->toISOString(),
                'data' => [
                    'markets' => $markets,
                    'statistics' => $statistics,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'type' => 'error',
                'message' => 'Failed to retrieve market data',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get price updates for specific symbols.
     */
    public function priceUpdates(Request $request): JsonResponse
    {
        try {
            $symbols = $request->get('symbols', []);
            
            if (empty($symbols)) {
                return response()->json([
                    'type' => 'error',
                    'message' => 'No symbols provided'
                ], 400);
            }

            $updates = [];
            foreach ($symbols as $symbol) {
                $stats = $this->marketService->calculate24hStats($symbol);
                $updates[] = [
                    'symbol' => $symbol,
                    'timestamp' => now()->toISOString(),
                    'data' => $stats
                ];
            }

            return response()->json([
                'type' => 'price_updates',
                'timestamp' => now()->toISOString(),
                'updates' => $updates
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'type' => 'error',
                'message' => 'Failed to retrieve price updates',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}