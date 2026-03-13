<?php

namespace App\Services\Contracts;

use App\Models\Wallet;

interface WalletManagerInterface
{
    /**
     * Get balance for user and cryptocurrency.
     */
    public function getBalance(int $userId, string $cryptocurrency): array;

    /**
     * Update balance for user and cryptocurrency.
     */
    public function updateBalance(int $userId, string $cryptocurrency, string $amount, string $reason): array;

    /**
     * Get portfolio value for user.
     */
    public function getPortfolioValue(int $userId): array;

    /**
     * Reserve balance for pending orders.
     */
    public function reserveBalance(int $userId, string $cryptocurrency, string $amount): string;

    /**
     * Release reserved balance.
     */
    public function releaseReservation(string $reservationId): bool;
}