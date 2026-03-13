<?php

namespace App\Services\Contracts;

use App\Models\Order;

interface TradingEngineInterface
{
    /**
     * Place a new order.
     */
    public function placeOrder(array $orderData): array;

    /**
     * Cancel an existing order.
     */
    public function cancelOrder(int $orderId, int $userId): array;

    /**
     * Get order book for cryptocurrency.
     */
    public function getOrderBook(string $cryptocurrency): array;

    /**
     * Get user orders.
     */
    public function getUserOrders(int $userId): array;

    /**
     * Process order matching for cryptocurrency.
     */
    public function processOrderMatching(string $cryptocurrency): array;
}