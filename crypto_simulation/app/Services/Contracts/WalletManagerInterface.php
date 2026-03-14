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
    public function updateBalance(int $userId, string $cryptocurrency, string $amount, string $reason, ?string $description = null): array;

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

    /**
     * Get wallet for user and cryptocurrency.
     */
    public function getWallet($user, string $cryptocurrency): ?Wallet;

    /**
     * Credit wallet with amount.
     */
    public function creditWallet($user, string $cryptocurrency, $amount, string $type, string $description): array;

    /**
     * Debit wallet with amount.
     */
    public function debitWallet($user, string $cryptocurrency, $amount, string $type, string $description): array;

    /**
     * Hold funds for pending transactions.
     */
    public function holdFunds($user, string $cryptocurrency, $amount, string $description): array;

    /**
     * Release held funds.
     */
    public function releaseFunds($user, string $cryptocurrency, $amount, string $description): array;
}