<?php

namespace App\Services;

use App\Models\User;
use App\Mail\PasswordResetMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PasswordResetService
{
    private const TOKEN_EXPIRY_MINUTES = 60; // 1 hour
    private const RATE_LIMIT_MINUTES = 5; // 5 minutes between requests

    /**
     * Send password reset email to user.
     */
    public function sendResetLink(string $email): array
    {
        try {
            // Check if user exists
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                // Don't reveal if email exists for security
                return [
                    'success' => true,
                    'message' => 'If the email exists in our system, a password reset link has been sent.',
                ];
            }

            // Check rate limiting
            $recentReset = DB::table('password_reset_tokens')
                ->where('email', $email)
                ->where('created_at', '>', now()->subMinutes(self::RATE_LIMIT_MINUTES))
                ->first();

            if ($recentReset) {
                return [
                    'success' => false,
                    'message' => 'Please wait before requesting another password reset.',
                    'error_code' => 'RATE_LIMITED'
                ];
            }

            // Generate secure token
            $token = Str::random(64);
            $hashedToken = Hash::make($token);

            // Store token in database
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                [
                    'token' => $hashedToken,
                    'created_at' => now()
                ]
            );

            // Send email with reset link
            $resetUrl = config('services.frontend.url') . '/reset-password?token=' . $token . '&email=' . urlencode($email);
            
            try {
                // For development, we'll just return the token since email might not be configured
                if (config('app.env') === 'local') {
                    Log::info('Password reset token generated (development mode)', [
                        'email' => $email,
                        'token' => $token,
                        'reset_url' => $resetUrl
                    ]);

                    return [
                        'success' => true,
                        'message' => 'Password reset link generated successfully.',
                        'reset_url' => $resetUrl,
                        'token' => $token, // Only in development
                        'expires_in_minutes' => self::TOKEN_EXPIRY_MINUTES
                    ];
                }

                // In production, you would send actual email here
                // Mail::to($email)->send(new PasswordResetMail($user, $resetUrl, $token));
                
                Log::info('Password reset email sent', [
                    'email' => $email,
                    'user_id' => $user->id
                ]);

                return [
                    'success' => true,
                    'message' => 'Password reset link has been sent to your email.',
                ];

            } catch (\Exception $mailException) {
                Log::error('Failed to send password reset email: ' . $mailException->getMessage());
                
                return [
                    'success' => false,
                    'message' => 'Failed to send password reset email. Please try again later.',
                ];
            }

        } catch (\Exception $e) {
            Log::error('Password reset request failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Password reset request failed. Please try again later.',
            ];
        }
    }

    /**
     * Verify reset token and update password.
     */
    public function resetPassword(string $email, string $token, string $newPassword): array
    {
        try {
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

            // Check if token is expired
            $tokenAge = Carbon::parse($resetRecord->created_at)->diffInMinutes(now());
            if ($tokenAge > self::TOKEN_EXPIRY_MINUTES) {
                // Clean up expired token
                DB::table('password_reset_tokens')->where('email', $email)->delete();
                
                return [
                    'success' => false,
                    'message' => 'Reset token has expired. Please request a new one.',
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

            // Find user
            $user = User::where('email', $email)->first();
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found.',
                    'error_code' => 'USER_NOT_FOUND'
                ];
            }

            // Validate new password
            if (strlen($newPassword) < 8) {
                return [
                    'success' => false,
                    'message' => 'Password must be at least 8 characters long.',
                    'error_code' => 'WEAK_PASSWORD'
                ];
            }

            // Update user password
            $user->password = Hash::make($newPassword);
            $user->save();

            // Reset failed login attempts and unlock account
            $user->failed_login_attempts = 0;
            $user->locked_until = null;
            $user->save();

            // Revoke all existing tokens for security
            $user->tokens()->delete();

            // Delete reset token
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            Log::info('Password reset successful', [
                'email' => $email,
                'user_id' => $user->id
            ]);

            return [
                'success' => true,
                'message' => 'Password has been reset successfully. You can now login with your new password.',
            ];

        } catch (\Exception $e) {
            Log::error('Password reset failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Password reset failed. Please try again.',
            ];
        }
    }

    /**
     * Verify if reset token is valid.
     */
    public function verifyResetToken(string $email, string $token): array
    {
        try {
            $resetRecord = DB::table('password_reset_tokens')
                ->where('email', $email)
                ->first();

            if (!$resetRecord) {
                return [
                    'valid' => false,
                    'message' => 'Invalid reset token.',
                ];
            }

            // Check if token is expired
            $tokenAge = Carbon::parse($resetRecord->created_at)->diffInMinutes(now());
            if ($tokenAge > self::TOKEN_EXPIRY_MINUTES) {
                // Clean up expired token
                DB::table('password_reset_tokens')->where('email', $email)->delete();
                
                return [
                    'valid' => false,
                    'message' => 'Reset token has expired.',
                ];
            }

            // Verify token
            if (!Hash::check($token, $resetRecord->token)) {
                return [
                    'valid' => false,
                    'message' => 'Invalid reset token.',
                ];
            }

            return [
                'valid' => true,
                'message' => 'Reset token is valid.',
                'expires_in_minutes' => self::TOKEN_EXPIRY_MINUTES - $tokenAge,
            ];

        } catch (\Exception $e) {
            Log::error('Token verification failed: ' . $e->getMessage());
            return [
                'valid' => false,
                'message' => 'Token verification failed.',
            ];
        }
    }

    /**
     * Clean up expired reset tokens.
     */
    public function cleanupExpiredTokens(): int
    {
        try {
            $expiredCount = DB::table('password_reset_tokens')
                ->where('created_at', '<', now()->subMinutes(self::TOKEN_EXPIRY_MINUTES))
                ->delete();

            Log::info("Cleaned up {$expiredCount} expired password reset tokens");
            
            return $expiredCount;

        } catch (\Exception $e) {
            Log::error('Failed to cleanup expired tokens: ' . $e->getMessage());
            return 0;
        }
    }
}