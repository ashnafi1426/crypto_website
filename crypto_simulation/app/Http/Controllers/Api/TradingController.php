<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\TradingEngineInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class TradingController extends Controller
{
    private TradingEngineInterface $tradingEngine;

    public function __construct(TradingEngineInterface $tradingEngine)
    {
        $this->tradingEngine = $tradingEngine;
        $this->middleware('auth:sanctum')->except(['orderBook']);
    }

    /**
     * Get user orders.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Add optional filtering parameters
            $validator = Validator::make($request->all(), [
                'status' => 'sometimes|in:pending,partial,filled,cancelled',
                'cryptocurrency_symbol' => 'sometimes|string|max:10|exists:cryptocurrencies,symbol',
                'limit' => 'sometimes|integer|min:1|max:100',
                'offset' => 'sometimes|integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $result = $this->tradingEngine->getUserOrders($user->id);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 500);
            }

            return response()->json([
                'success' => true,
                'orders' => $result['orders'],
                'total_count' => $result['total_count'] ?? count($result['orders'])
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to retrieve user orders', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve orders'
            ], 500);
        }
    }

    /**
     * Place a new order.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'cryptocurrency_symbol' => 'required|string|max:10|exists:cryptocurrencies,symbol',
                'order_type' => 'required|in:market,limit',
                'side' => 'required|in:buy,sell',
                'quantity' => 'required|numeric|min:0.00000001|max:999999999.99999999',
                'price' => 'required_if:order_type,limit|numeric|min:0.00000001|max:999999999.99999999'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            
            // Format numeric values to 8 decimal places
            $quantity = number_format((float)$request->quantity, 8, '.', '');
            $price = $request->price ? number_format((float)$request->price, 8, '.', '') : null;
            
            $orderData = [
                'user_id' => $user->id,
                'cryptocurrency_symbol' => strtoupper($request->cryptocurrency_symbol),
                'order_type' => $request->order_type,
                'side' => $request->side,
                'quantity' => $quantity,
                'price' => $price
            ];

            $result = $this->tradingEngine->placeOrder($orderData);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'order' => $result['order']
            ], 201);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Order placement failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to place order'
            ], 500);
        }
    }

    /**
     * Cancel an order.
     */
    public function cancel(Request $request, int $orderId): JsonResponse
    {
        try {
            // Validate order ID
            if ($orderId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid order ID'
                ], 400);
            }

            $user = $request->user();
            $result = $this->tradingEngine->cancelOrder($orderId, $user->id);

            if (!$result['success']) {
                $statusCode = match($result['message']) {
                    'Order not found' => 404,
                    'Unauthorized to cancel this order' => 403,
                    'Order cannot be cancelled' => 400,
                    default => 400
                };
                
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], $statusCode);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'order' => $result['order'] ?? null
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Order cancellation failed', [
                'user_id' => $request->user()?->id,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order'
            ], 500);
        }
    }

    /**
     * Get order book for cryptocurrency.
     */
    public function orderBook(Request $request, string $cryptocurrency): JsonResponse
    {
        try {
            // Validate cryptocurrency symbol
            $validator = Validator::make(['cryptocurrency' => $cryptocurrency], [
                'cryptocurrency' => 'required|string|max:10|exists:cryptocurrencies,symbol'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid cryptocurrency symbol',
                    'errors' => $validator->errors()
                ], 400);
            }

            $result = $this->tradingEngine->getOrderBook(strtoupper($cryptocurrency));

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 500);
            }

            return response()->json([
                'success' => true,
                'cryptocurrency' => $result['cryptocurrency'],
                'bids' => $result['bids'],
                'asks' => $result['asks'],
                'last_updated' => $result['last_updated'],
                'spread' => $result['spread'] ?? null,
                'best_bid' => $result['best_bid'] ?? null,
                'best_ask' => $result['best_ask'] ?? null
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Order book retrieval failed', [
                'cryptocurrency' => $cryptocurrency,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve order book'
            ], 500);
        }
    }
}