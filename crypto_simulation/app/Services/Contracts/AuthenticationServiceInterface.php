<?php

namespace App\Services\Contracts;

use App\Models\User;

interface AuthenticationServiceInterface
{
    /**
     * Authenticate user with email and password.
     */
    public function authenticate(string $email, string $password): array;

    /**
     * Generate JWT token for user.
     */
    public function generateToken(User $user): string;

    /**
     * Validate JWT token.
     */
    public function validateToken(string $token): array;

    /**
     * Revoke JWT token.
     */
    public function revokeToken(string $token): bool;

    /**
     * Check rate limit for user.
     */
    public function checkRateLimit(string $identifier): array;

    /**
     * Register a new user with validation and wallet initialization.
     */
    public function register(array $userData): array;

    /**
     * Request password reset.
     */
    public function requestPasswordReset(string $email): array;

    /**
     * Confirm password reset.
     */
    public function confirmPasswordReset(string $email, string $token, string $newPassword): array;
}