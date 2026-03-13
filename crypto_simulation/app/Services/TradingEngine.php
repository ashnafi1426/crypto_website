<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Trade;
use App\Models\Cryptocurrency;
use App\Services\Contracts\TradingEngineInterface;
use App\Services\Contracts\WalletManagerInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TradingEngine implements TradingEngineInterface
{
    private WalletManagerInterface $walletManager;

    public function __construct(WalletManagerInterface $walletManager)
    {
        $this->walletManager = $walletManager;
    }

    /**
     * Place a new order.
     */
    public function placeOrder(array $orderData): array
    {
        try {
            DB::beginTransaction();

            // Validate cryptocurrency exists
            $crypto = Cryptocurrency::where('symbol', $orderData['cryptocurrency_symbol'])->first();
            if (!$crypto) {
                return [
                    'success' => false,
                    'message' => 'Cryptocurrency not found'
                ];
            }

            // For market orders, use current market price
            $price = $orderData['price'];
            if ($orderData['order_type'] === 'market') {
                $price = $crypto->current_price;
            }

            // Calculate total amount needed
            $totalAmount = bcmul($orderData['quantity'], $price, 8);

            // For buy orders, reserve USD; for sell orders, reserve cryptocurrency
            if ($orderData['side'] === 'buy') {
                $reservationId = $this->walletManager->reserveBalance(
                    $orderData['user_id'],
                    'USD',
                    $totalAmount
                );
            } else {
                $reservationId = $this->walletManager->reserveBalance(
                    $orderData['user_id'],
                    $orderData['cryptocurrency_symbol'],
                    $orderData['quantity']
                );
            }

            if (empty($reservationId)) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Insufficient balance'
                ];
            }

            // Create order
            $order = Order::create([
                'user_id' => $orderData['user_id'],
                'cryptocurrency_symbol' => $orderData['cryptocurrency_symbol'],
                'order_type' => $orderData['order_type'], // market or limit
                'side' => $orderData['side'], // buy or sell
                'quantity' => $orderData['quantity'],
                'price' => $price,
                'status' => 'pending',
                'reservation_id' => $reservationId,
                'created_at' => now()
            ]);

            DB::commit();

            // Queue order matching for asynchronous processing
            \App\Jobs\ProcessOrderMatching::dispatch($orderData['cryptocurrency_symbol'])
                ->onQueue('trading');

            return [
                'success' => true,
                'message' => 'Order placed successfully',
                'order_id' => $order->id,
                'order' => $order->toArray()
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to place order', [
                'order_data' => $orderData,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to place order due to server error'
            ];
        }
    }

    /**
     * Cancel an existing order.
     */
    public function cancelOrder(int $orderId, int $userId): array
    {
        try {
            DB::beginTransaction();

            $order = Order::where('id', $orderId)
                ->where('user_id', $userId)
                ->whereIn('status', ['pending', 'partial'])
                ->lockForUpdate()
                ->first();

            if (!$order) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Order not found or already processed'
                ];
            }

            // Release reserved balance
            if ($order->reservation_id) {
                $this->walletManager->releaseReservation($order->reservation_id);
            }

            // Update order status
            $order->update([
                'status' => 'cancelled',
                'updated_at' => now()
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Order cancelled successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to cancel order', [
                'order_id' => $orderId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to cancel order due to server error'
            ];
        }
    }

    /**
     * Get order book for cryptocurrency.
     */
    public function getOrderBook(string $cryptocurrency): array
    {
        try {
            // Get buy orders (bids) - highest price first
            $bids = Order::where('cryptocurrency_symbol', $cryptocurrency)
                ->where('side', 'buy')
                ->whereIn('status', ['pending', 'partial'])
                ->orderBy('price', 'desc')
                ->orderBy('created_at', 'asc')
                ->select('price', DB::raw('SUM(quantity - filled_quantity) as total_quantity'))
                ->groupBy('price')
                ->limit(20)
                ->get()
                ->map(function ($order) {
                    return [
                        'price' => $order->price,
                        'quantity' => $order->total_quantity,
                        'total' => bcmul($order->price, $order->total_quantity, 8)
                    ];
                });

            // Get sell orders (asks) - lowest price first
            $asks = Order::where('cryptocurrency_symbol', $cryptocurrency)
                ->where('side', 'sell')
                ->whereIn('status', ['pending', 'partial'])
                ->orderBy('price', 'asc')
                ->orderBy('created_at', 'asc')
                ->select('price', DB::raw('SUM(quantity - filled_quantity) as total_quantity'))
                ->groupBy('price')
                ->limit(20)
                ->get()
                ->map(function ($order) {
                    return [
                        'price' => $order->price,
                        'quantity' => $order->total_quantity,
                        'total' => bcmul($order->price, $order->total_quantity, 8)
                    ];
                });

            return [
                'success' => true,
                'cryptocurrency' => $cryptocurrency,
                'bids' => $bids->toArray(),
                'asks' => $asks->toArray(),
                'last_updated' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get order book', [
                'cryptocurrency' => $cryptocurrency,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve order book',
                'bids' => [],
                'asks' => []
            ];
        }
    }

    /**
     * Get user orders.
     */
    public function getUserOrders(int $userId): array
    {
        try {
            $orders = Order::where('user_id', $userId)
                ->with('cryptocurrency')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'cryptocurrency_symbol' => $order->cryptocurrency_symbol,
                        'cryptocurrency_name' => $order->cryptocurrency->name ?? $order->cryptocurrency_symbol,
                        'order_type' => $order->order_type,
                        'side' => $order->side,
                        'quantity' => $order->quantity,
                        'price' => $order->price,
                        'filled_quantity' => $order->filled_quantity,
                        'remaining_quantity' => bcsub($order->quantity, $order->filled_quantity, 8),
                        'total' => bcmul($order->quantity, $order->price, 8),
                        'status' => $order->status,
                        'created_at' => $order->created_at->toISOString(),
                        'updated_at' => $order->updated_at->toISOString()
                    ];
                });

            return [
                'success' => true,
                'orders' => $orders->toArray()
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get user orders', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve orders',
                'orders' => []
            ];
        }
    }

    /**
     * Process order matching for cryptocurrency.
     */
    public function processOrderMatching(string $cryptocurrency): array
    {
        try {
            DB::beginTransaction();

            $matchesCount = 0;

            // Get the best buy order (highest price)
            $buyOrder = Order::where('cryptocurrency_symbol', $cryptocurrency)
                ->where('side', 'buy')
                ->whereIn('status', ['pending', 'partial'])
                ->orderBy('price', 'desc')
                ->orderBy('created_at', 'asc')
                ->lockForUpdate()
                ->first();

            // Get the best sell order (lowest price)
            $sellOrder = Order::where('cryptocurrency_symbol', $cryptocurrency)
                ->where('side', 'sell')
                ->whereIn('status', ['pending', 'partial'])
                ->orderBy('price', 'asc')
                ->orderBy('created_at', 'asc')
                ->lockForUpdate()
                ->first();

            // Check if orders can be matched
            while ($buyOrder && $sellOrder && bccomp($buyOrder->price, $sellOrder->price, 8) >= 0) {
                // Calculate remaining quantities
                $buyRemaining = bcsub($buyOrder->quantity, $buyOrder->filled_quantity, 8);
                $sellRemaining = bcsub($sellOrder->quantity, $sellOrder->filled_quantity, 8);
                
                // Determine trade quantity (minimum of both remaining quantities)
                $tradeQuantity = bccomp($buyRemaining, $sellRemaining, 8) <= 0 
                    ? $buyRemaining 
                    : $sellRemaining;

                // Use the sell order price as the trade price
                $tradePrice = $sellOrder->price;
                $tradeTotal = bcmul($tradeQuantity, $tradePrice, 8);

                // Create trade record
                $trade = Trade::create([
                    'buy_order_id' => $buyOrder->id,
                    'sell_order_id' => $sellOrder->id,
                    'buyer_id' => $buyOrder->user_id,
                    'seller_id' => $sellOrder->user_id,
                    'cryptocurrency_symbol' => $cryptocurrency,
                    'quantity' => $tradeQuantity,
                    'price' => $tradePrice,
                    'total' => $tradeTotal,
                    'trade_id' => Str::uuid(),
                    'executed_at' => now()
                ]);

                // Update buyer's balances (give crypto, take USD)
                $this->walletManager->updateBalance(
                    $buyOrder->user_id,
                    $cryptocurrency,
                    $tradeQuantity,
                    "Purchase from trade {$trade->trade_id}"
                );

                $this->walletManager->updateBalance(
                    $buyOrder->user_id,
                    'USD',
                    '-' . $tradeTotal,
                    "Payment for trade {$trade->trade_id}"
                );

                // Update seller's balances (give USD, take crypto)
                $this->walletManager->updateBalance(
                    $sellOrder->user_id,
                    'USD',
                    $tradeTotal,
                    "Sale from trade {$trade->trade_id}"
                );

                $this->walletManager->updateBalance(
                    $sellOrder->user_id,
                    $cryptocurrency,
                    '-' . $tradeQuantity,
                    "Sale from trade {$trade->trade_id}"
                );

                // Update order filled quantities
                $buyOrder->filled_quantity = bcadd($buyOrder->filled_quantity, $tradeQuantity, 8);
                $sellOrder->filled_quantity = bcadd($sellOrder->filled_quantity, $tradeQuantity, 8);

                // Mark orders as filled if quantity is zero
                if (bccomp($buyOrder->quantity, $buyOrder->filled_quantity, 8) == 0) {
                    $buyOrder->status = 'filled';
                    if ($buyOrder->reservation_id) {
                        $this->walletManager->releaseReservation($buyOrder->reservation_id);
                    }
                }

                if (bccomp($sellOrder->quantity, $sellOrder->filled_quantity, 8) == 0) {
                    $sellOrder->status = 'filled';
                    if ($sellOrder->reservation_id) {
                        $this->walletManager->releaseReservation($sellOrder->reservation_id);
                    }
                }

                $buyOrder->save();
                $sellOrder->save();

                $matchesCount++;

                // Get next orders if current ones are filled
                if ($buyOrder->status === 'filled') {
                    $buyOrder = Order::where('cryptocurrency_symbol', $cryptocurrency)
                        ->where('side', 'buy')
                        ->whereIn('status', ['pending', 'partial'])
                        ->orderBy('price', 'desc')
                        ->orderBy('created_at', 'asc')
                        ->lockForUpdate()
                        ->first();
                }

                if ($sellOrder->status === 'filled') {
                    $sellOrder = Order::where('cryptocurrency_symbol', $cryptocurrency)
                        ->where('side', 'sell')
                        ->whereIn('status', ['pending', 'partial'])
                        ->orderBy('price', 'asc')
                        ->orderBy('created_at', 'asc')
                        ->lockForUpdate()
                        ->first();
                }
            }

            DB::commit();

            return [
                'success' => true,
                'matches' => $matchesCount,
                'cryptocurrency' => $cryptocurrency
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process order matching', [
                'cryptocurrency' => $cryptocurrency,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to process order matching',
                'matches' => 0
            ];
        }
    }
}