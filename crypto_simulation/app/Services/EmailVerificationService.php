<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

class EmailVerificationService
{
    const MAX_VERIFICATION_ATTEMPTS = 5;
    const VERIFICATION_EXPIRY_HOURS = 24;
    const RESEND_COOLDOWN_MINUTES = 5;

    /**
     * Send email verification
     */
    public function sendVerificationEmail(User $user): array
    {
        try {
            // Check if user is already verified
            if ($user->hasVerifiedEmail()) {
                return [
                    'success' => false,
                    'message' => 'Email is already verified'
                ];
            }

            // Check rate limiting
            if ($this->isRateLimited($user)) {
                return [
                    'success' => false,
                    'message' => 'Please wait before requesting another verification email'
                ];
            }

            // Check max attempts
            if ($user->email_verification_attempts >= self::MAX_VERIFICATION_ATTEMPTS) {
                return [
                    'success' => false,
                    'message' => 'Maximum verification attempts exceeded. Please contact support.'
                ];
            }

            // Generate verification token
            $token = Str::random(64);
            
            // Update user with verification token
            $user->update([
                'email_verification_token' => hash('sha256', $token),
                'email_verification_sent_at' => now(),
                'email_verification_attempts' => $user->email_verification_attempts + 1
            ]);

            // Send email (mock implementation)
            $this->sendVerificationEmailMock($user, $token);

            return [
                'success' => true,
                'message' => 'Verification email sent successfully',
                'token' => $token // For testing purposes only
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to send verification email: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify email with token
     */
    public function verifyEmail(string $token): array
    {
        try {
            $hashedToken = hash('sha256', $token);
            
            $user = User::where('email_verification_token', $hashedToken)
                       ->whereNull('email_verified_at')
                       ->first();

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired verification token'
                ];
            }

            // Check if token is expired
            if ($user->email_verification_sent_at->addHours(self::VERIFICATION_EXPIRY_HOURS)->isPast()) {
                return [
                    'success' => false,
                    'message' => 'Verification token has expired'
                ];
            }

            // Mark email as verified
            $user->update([
                'email_verified_at' => now(),
                'email_verification_token' => null,
                'email_verification_sent_at' => null,
                'email_verification_attempts' => 0
            ]);

            return [
                'success' => true,
                'message' => 'Email verified successfully',
                'user' => $user
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Email verification failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check if user is rate limited
     */
    private function isRateLimited(User $user): bool
    {
        if (!$user->email_verification_sent_at) {
            return false;
        }

        return $user->email_verification_sent_at
                   ->addMinutes(self::RESEND_COOLDOWN_MINUTES)
                   ->isFuture();
    }

    /**
     * Mock email sending (replace with real email service)
     */
    private function sendVerificationEmailMock(User $user, string $token): void
    {
        try {
            // Send real email using Laravel Mail
            \Mail::to($user->email)->send(new \App\Mail\EmailVerificationMail($user, $token));
            
            \Log::info('Email Verification Sent', [
                'user_id' => $user->id,
                'email' => $user->email,
                'token' => substr($token, 0, 10) . '...',
                'verification_url' => url("/api/auth/verify-email?token={$token}")
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to send verification email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get verification status
     */
    public function getVerificationStatus(User $user): array
    {
        return [
            'is_verified' => $user->hasVerifiedEmail(),
            'verification_sent_at' => $user->email_verification_sent_at,
            'attempts_remaining' => max(0, self::MAX_VERIFICATION_ATTEMPTS - $user->email_verification_attempts),
            'can_resend' => !$this->isRateLimited($user),
            'next_resend_at' => $user->email_verification_sent_at 
                ? $user->email_verification_sent_at->addMinutes(self::RESEND_COOLDOWN_MINUTES)
                : null
        ];
    }
}