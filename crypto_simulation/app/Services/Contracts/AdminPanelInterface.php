<?php

namespace App\Services\Contracts;

interface AdminPanelInterface
{
    /**
     * Get system metrics.
     */
    public function getSystemMetrics(): array;

    /**
     * Adjust user balance.
     */
    public function adjustUserBalance(int $userId, string $cryptocurrency, string $amount): array;

    /**
     * Flag suspicious activity.
     */
    public function flagSuspiciousActivity(int $userId, string $reason): bool;

    /**
     * Generate daily report.
     */
    public function generateDailyReport(\DateTime $date): array;

    /**
     * Override price for cryptocurrency.
     */
    public function overridePrice(string $cryptocurrency, string $price): bool;
}