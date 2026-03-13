<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Cryptocurrency;
use App\Services\Contracts\AuthenticationServiceInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

class AuthenticationService implements AuthenticationServiceInterface
{
    private const RATE_LIMIT_KEY = 'auth_rate_limit:';
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;
    private const RATE_LIMIT_REQUESTS = 100;
    private const RATE_LIMIT_MINUTES = 60;

    public function authenticate(string $email, string $password): array
    {
        try {
            // Check rate limiting
            $rateLimitResult = $this->checkRateLimit($email);
            if (!$rateLimitResult['allowed']) {
                return [
                    'success' => false,
                    'message' => 'Rate limit exceeded. Please try again later.',
                    'error_code' => 'RATE_LIMIT_EXCEEDED'
                ];
            }

            // Find user by email
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Invalid credentials.',
                    'error_code' => 'INVALID_CREDENTIALS'
                ];
            }

            // Check if account is locked
            if ($user->isLocked()) {
                return [
                    'success' => false,
                    'message' => 'Account is temporarily locked due to multiple failed login attempts.',
                    'error_code' => 'ACCOUNT_LOCKED',
                    'locked_until' => $user->locked_until->toISOString()
                ];
            }

            // Verify password
            if (!Hash::check($password, $user->password)) {
                // Increment failed attempts
                $this->handleFailedLogin($user);
                
                return [
                    'success' => false,
                    'message' => 'Invalid credentials.',
                    'error_code' => 'INVALID_CREDENTIALS'
                ];
            }

            // Reset failed attempts on successful login
            $user->resetFailedAttempts();

            // Generate token
            $token = $this->generateToken($user);

            return [
                'success' => true,
                'message' => 'Authentication successful.',
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_admin' => $user->is_admin,
                    'email_verified_at' => $user->email_verified_at
                ]
            ];

        } catch (\Exception) {
            return [
                'success' => false,
                'message' => 'Authentication failed due to server error.',
                'error_code' => 'SERVER_ERROR'
            ];
        }
    }

    public function generateToken(User $user): string
    {
        // Revoke existing tokens for security
        $user->tokens()->delete();

        // Create new token with 24-hour expiration
        $token = $user->createToken(
            'crypto-exchange-token',
            ['*'],
            now()->addHours(24)
        );

        return $token->plainTextToken;
    }

    public function validateToken(string $token): array
    {
        try {
            // Find the token
            $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);

            if (!$personalAccessToken) {
                return [
                    'valid' => false,
                    'message' => 'Token not found.',
                    'error_code' => 'TOKEN_NOT_FOUND'
                ];
            }

            // Check if token is expired
            if ($personalAccessToken->expires_at?->isPast()) {
                return [
                    'valid' => false,
                    'message' => 'Token has expired.',
                    'error_code' => 'TOKEN_EXPIRED'
                ];
            }

            // Get the user
            $user = $personalAccessToken->tokenable;

            if (!$user) {
                return [
                    'valid' => false,
                    'message' => 'User not found.',
                    'error_code' => 'USER_NOT_FOUND'
                ];
            }

            // Check if user account is locked
            if ($user->isLocked()) {
                return [
                    'valid' => false,
                    'message' => 'User account is locked.',
                    'error_code' => 'ACCOUNT_LOCKED'
                ];
            }

            return [
                'valid' => true,
                'user' => $user,
                'token' => $personalAccessToken
            ];

        } catch (\Exception) {
            return [
                'valid' => false,
                'message' => 'Token validation failed.',
                'error_code' => 'VALIDATION_ERROR'
            ];
        }
    }

    public function revokeToken(string $token): bool
    {
        try {
            $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            
            if ($personalAccessToken) {
                $personalAccessToken->delete();
                return true;
            }

            return false;
        } catch (\Exception) {
            return false;
        }
    }

    public function checkRateLimit(string $identifier): array
    {
        try {
            $key = self::RATE_LIMIT_KEY . $identifier;
            $attempts = Cache::get($key, 0);

            if ($attempts >= self::RATE_LIMIT_REQUESTS) {
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'reset_at' => now()->addMinutes(self::RATE_LIMIT_MINUTES)->toISOString()
                ];
            }

            // Increment attempts
            Cache::put($key, $attempts + 1, now()->addMinutes(self::RATE_LIMIT_MINUTES));

            return [
                'allowed' => true,
                'remaining' => self::RATE_LIMIT_REQUESTS - ($attempts + 1),
                'reset_at' => now()->addMinutes(self::RATE_LIMIT_MINUTES)->toISOString()
            ];
        } catch (\Exception $e) {
            // If cache fails, allow the request but log the error
            Log::warning('Cache failure in rate limiting: ' . $e->getMessage());
            return [
                'allowed' => true,
                'remaining' => self::RATE_LIMIT_REQUESTS,
                'reset_at' => now()->addMinutes(self::RATE_LIMIT_MINUTES)->toISOString()
            ];
        }
    }

    /**
     * Register a new user with validation and wallet initialization.
     */
    public function register(array $userData): array
    {
        try {
            // Validate input data
            $validator = Validator::make($userData, [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => [
                    'required',
                    'confirmed',
                    Password::min(8)
                        ->letters()
                        ->mixedCase()
                        ->numbers()
                        ->symbols()
                ],
            ]);

            if ($validator->fails()) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()->toArray(),
                    'error_code' => 'VALIDATION_ERROR'
                ];
            }

            // Check rate limiting for registration
            $rateLimitResult = $this->checkRateLimit("register:{$userData['email']}");
            if (!$rateLimitResult['allowed']) {
                return [
                    'success' => false,
                    'message' => 'Registration rate limit exceeded.',
                    'error_code' => 'RATE_LIMIT_EXCEEDED'
                ];
            }

            DB::beginTransaction();

            // Create user
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make($userData['password']),
                'is_admin' => false,
                'failed_login_attempts' => 0,
            ]);

            // Initialize wallets for all active cryptocurrencies
            $this->initializeUserWallets($user);

            DB::commit();

            // Generate token
            $token = $this->generateToken($user);

            return [
                'success' => true,
                'message' => 'User registered successfully.',
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_admin' => $user->is_admin,
                    'email_verified_at' => $user->email_verified_at
                ]
            ];

        } catch (\Exception) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => 'Registration failed due to server error.',
                'error_code' => 'SERVER_ERROR'
            ];
        }
    }

    /**
     * Handle failed login attempt.
     */
    private function handleFailedLogin(User $user): void
    {
        $user->increment('failed_login_attempts');

        if ($user->failed_login_attempts >= self::MAX_ATTEMPTS) {
            $user->lockAccount(self::LOCKOUT_MINUTES);
        }
    }

    /**
     * Initialize wallets for all active cryptocurrencies.
     */
    private function initializeUserWallets(User $user): void
    {
        $activeCryptocurrencies = Cryptocurrency::where('is_active', true)->get();

        foreach ($activeCryptocurrencies as $crypto) {
            // Skip if wallet already exists
            $existingWallet = Wallet::where('user_id', $user->id)
                ->where('cryptocurrency_symbol', $crypto->symbol)
                ->first();
                
            if (!$existingWallet) {
                $balance = $crypto->symbol === 'USD' ? '10000.00000000' : '0.00000000';
                
                Wallet::create([
                    'user_id' => $user->id,
                    'cryptocurrency_symbol' => $crypto->symbol,
                    'balance' => $balance,
                    'reserved_balance' => '0.00000000',
                ]);
            }
        }
    }

    /**
     * Request password reset.
     */
    public function requestPasswordReset(string $email): array
    {
        try {
            $user = User::where('email', $email)->first();

            if (!$user) {
                // Don't reveal if email exists for security
                return [
                    'success' => true,
                    'message' => 'If the email exists, a password reset link has been sent.'
                ];
            }

            // Check rate limiting
            $rateLimitResult = $this->checkRateLimit("password_reset:{$email}");
            if (!$rateLimitResult['allowed']) {
                return [
                    'success' => false,
                    'message' => 'Too many password reset requests. Please try again later.',
                    'error_code' => 'RATE_LIMIT_EXCEEDED'
                ];
            }

            // Generate reset token (30-minute expiration)
            $token = \Illuminate\Support\Str::random(64);
            
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                [
                    'token' => Hash::make($token),
                    'created_at' => now()
                ]
            );

            // TODO: Send email with reset link
            // For now, we'll just return the token (in production, this should be sent via email)
            
            return [
                'success' => true,
                'message' => 'Password reset link has been sent to your email.',
                'reset_token' => $token // Remove this in production
            ];

        } catch (\Exception) {
            return [
                'success' => false,
                'message' => 'Password reset request failed.',
                'error_code' => 'SERVER_ERROR'
            ];
        }
    }

    /**
     * Confirm password reset.
     */
    public function confirmPasswordReset(string $email, string $token, string $newPassword): array
    {
        try {
            // Validate new password
            $validator = Validator::make(['password' => $newPassword], [
                'password' => [
                    'required',
                    Password::min(8)
                        ->letters()
                        ->mixedCase()
                        ->numbers()
                        ->symbols()
                ],
            ]);

            if ($validator->fails()) {
                return [
                    'success' => false,
                    'message' => 'Password validation failed.',
                    'errors' => $validator->errors()->toArray(),
                    'error_code' => 'VALIDATION_ERROR'
                ];
            }

            // Find reset token
            $resetRecord = DB::table('password_reset_tokens')
                ->where('email', $email)
                ->first();

            if (!$resetRecord) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired reset token.',
                    'error_code' => 'INVALID_TOKEN'
                ];
            }

            // Check if token is expired (30 minutes)
            if (now()->diffInMinutes($resetRecord->created_at) > 30) {
                DB::table('password_reset_tokens')->where('email', $email)->delete();
                
                return [
                    'success' => false,
                    'message' => 'Reset token has expired.',
                    'error_code' => 'TOKEN_EXPIRED'
                ];
            }

            // Verify token
            if (!Hash::check($token, $resetRecord->token)) {
                return [
                    'success' => false,
                    'message' => 'Invalid reset token.',
                    'error_code' => 'INVALID_TOKEN'
                ];
            }

            // Update user password
            $user = User::where('email', $email)->first();
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found.',
                    'error_code' => 'USER_NOT_FOUND'
                ];
            }

            $user->update([
                'password' => Hash::make($newPassword)
            ]);

            // Reset failed attempts and unlock account
            $user->resetFailedAttempts();

            // Revoke all existing tokens for security
            $user->tokens()->delete();

            // Delete reset token
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return [
                'success' => true,
                'message' => 'Password has been reset successfully.'
            ];

        } catch (\Exception) {
            return [
                'success' => false,
                'message' => 'Password reset failed.',
                'error_code' => 'SERVER_ERROR'
            ];
        }
    }
}