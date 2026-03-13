<?php

namespace App\Services\Contracts;

interface PriceSimulatorInterface
{
    /**
     * Update prices for all cryptocurrencies.
     */
    public function updatePrices(): array;

    /**
     * Get current price for cryptocurrency.
     */
    public function getCurrentPrice(string $cryptocurrency): array;

    /**
     * Get price history for cryptocurrency.
     */
    public function getPriceHistory(string $cryptocurrency, array $dateRange): array;

    /**
     * Set volatility for cryptocurrency.
     */
    public function setCryptocurrencyVolatility(string $cryptocurrency, float $volatility): bool;

    /**
     * Generate candlestick data.
     */
    public function generateCandlestickData(string $cryptocurrency, string $interval): array;
}