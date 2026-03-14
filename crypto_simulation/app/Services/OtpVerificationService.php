<?php

namespace App\Services;

use App\Models\User;
use App\Models\OtpVerification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Carbon\Carbon;

class OtpVerificationService
{
    const OTP_LENGTH = 6;
    const OTP_EXPIRY_MINUTES = 10;
    const MAX_ATTEMPTS = 3;
    const RESEND_COOLDOWN_SECONDS = 60;
    const MAX_DAILY_OTPS = 10;

    /**
     * Generate and send OTP
     */
    public function generateOtp(
        User $user, 
        string $identifier, 
        string $type, 
        string $purpose, 
        ?Request $request = null
    ): array {
        try {
            // Check rate limiting
            if ($this->isRateLimited($user, $type, $purpose)) {
                return [
                    'success' => false,
                    'message' => 'Please wait before requesting another OTP'
                ];
            }

            // Check daily limit
            if ($this->isDailyLimitExceeded($user, $type)) {
                return [
                    'success' => false,
                    'message' => 'Daily OTP limit exceeded. Please try again tomorrow.'
                ];
            }

            // Invalidate previous OTPs for same purpose
            $this->invalidatePreviousOtps($user, $identifier, $type, $purpose);

            // Generate OTP code
            $otpCode = $this->generateOtpCode();

            // Create OTP record
            $otp = OtpVerification::create([
                'user_id' => $user->id,
                'identifier' => $identifier,
                'otp_code' => $otpCode,
                'type' => $type,
                'purpose' => $purpose,
                'expires_at' => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent()
            ]);

            // Send OTP
            $sent = $this->sendOtp($identifier, $otpCode, $type, $purpose, $user);

            if ($sent) {
                return [
                    'success' => true,
                    'message' => 'OTP sent successfully',
                    'expires_in' => self::OTP_EXPIRY_MINUTES * 60,
                    'otp_id' => $otp->id,
                    // For testing purposes only - remove in production
                    'otp_code' => config('app.env') === 'local' ? $otpCode : null
                ];
            } else {
                $otp->delete();
                return [
                    'success' => false,
                    'message' => 'Failed to send OTP'
                ];
            }

        } catch (\Exception $e) {
            Log::error('OTP generation failed', [
                'user_id' => $user->id,
                'type' => $type,
                'purpose' => $purpose,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to generate OTP: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify OTP code
     */
    public function verifyOtp(
        User $user, 
        string $identifier, 
        string $otpCode, 
        string $type, 
        string $purpose
    ): array {
        try {
            // Find valid OTP
            $otp = OtpVerification::where('user_id', $user->id)
                ->where('identifier', $identifier)
                ->where('type', $type)
                ->where('purpose', $purpose)
                ->valid()
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$otp) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired OTP'
                ];
            }

            // Check attempts
            if ($otp->attempts >= self::MAX_ATTEMPTS) {
                return [
                    'success' => false,
                    'message' => 'Maximum verification attempts exceeded'
                ];
            }

            // Verify code
            if ($otp->otp_code !== $otpCode) {
                $otp->incrementAttempts();
                return [
                    'success' => false,
                    'message' => 'Invalid OTP code',
                    'attempts_remaining' => max(0, self::MAX_ATTEMPTS - $otp->attempts - 1)
                ];
            }

            // Mark as used
            $otp->markAsUsed();

            // Log successful verification
            Log::info('OTP verified successfully', [
                'user_id' => $user->id,
                'type' => $type,
                'purpose' => $purpose,
                'otp_id' => $otp->id
            ]);

            return [
                'success' => true,
                'message' => 'OTP verified successfully',
                'otp_id' => $otp->id
            ];

        } catch (\Exception $e) {
            Log::error('OTP verification failed', [
                'user_id' => $user->id,
                'type' => $type,
                'purpose' => $purpose,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'OTP verification failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get OTP status
     */
    public function getOtpStatus(User $user, string $identifier, string $type, string $purpose): array
    {
        $otp = OtpVerification::where('user_id', $user->id)
            ->where('identifier', $identifier)
            ->where('type', $type)
            ->where('purpose', $purpose)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$otp) {
            return [
                'has_active_otp' => false,
                'can_resend' => true
            ];
        }

        return [
            'has_active_otp' => $otp->isValid(),
            'expires_at' => $otp->expires_at,
            'attempts_used' => $otp->attempts,
            'attempts_remaining' => max(0, self::MAX_ATTEMPTS - $otp->attempts),
            'can_resend' => !$this->isRateLimited($user, $type, $purpose),
            'next_resend_at' => $this->getNextResendTime($user, $type, $purpose)
        ];
    }

    /**
     * Generate OTP code
     */
    private function generateOtpCode(): string
    {
        return str_pad(random_int(0, 999999), self::OTP_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Send OTP via specified method
     */
    private function sendOtp(string $identifier, string $otpCode, string $type, string $purpose, User $user): bool
    {
        try {
            switch ($type) {
                case 'email':
                    return $this->sendEmailOtp($identifier, $otpCode, $purpose, $user);
                case 'sms':
                    return $this->sendSmsOtp($identifier, $otpCode, $purpose, $user);
                default:
                    return false;
            }
        } catch (\Exception $e) {
            Log::error('Failed to send OTP', [
                'type' => $type,
                'identifier' => $identifier,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send OTP via email
     */
    private function sendEmailOtp(string $email, string $otpCode, string $purpose, User $user): bool
    {
        try {
            // Send real email using Laravel Mail
            Mail::to($email)->send(new \App\Mail\OtpMail($otpCode, $purpose, $user, self::OTP_EXPIRY_MINUTES));
            
            Log::info('Email OTP sent', [
                'email' => $email,
                'purpose' => $purpose,
                'user_id' => $user->id,
                'otp_length' => strlen($otpCode)
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send email OTP', [
                'email' => $email,
                'purpose' => $purpose,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send OTP via SMS
     */
    private function sendSmsOtp(string $phone, string $otpCode, string $purpose, User $user): bool
    {
        // Mock SMS sending - replace with real SMS service (Twilio, etc.)
        Log::info('SMS OTP sent', [
            'phone' => $phone,
            'otp_code' => $otpCode,
            'purpose' => $purpose,
            'user_id' => $user->id
        ]);

        // In production, integrate with SMS service:
        // $this->smsService->send($phone, "Your OTP: {$otpCode}");
        
        return true;
    }

    /**
     * Check if user is rate limited
     */
    private function isRateLimited(User $user, string $type, string $purpose): bool
    {
        $lastOtp = OtpVerification::where('user_id', $user->id)
            ->where('type', $type)
            ->where('purpose', $purpose)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$lastOtp) {
            return false;
        }

        return $lastOtp->created_at->addSeconds(self::RESEND_COOLDOWN_SECONDS)->isFuture();
    }

    /**
     * Check if daily limit is exceeded
     */
    private function isDailyLimitExceeded(User $user, string $type): bool
    {
        $todayCount = OtpVerification::where('user_id', $user->id)
            ->where('type', $type)
            ->whereDate('created_at', today())
            ->count();

        return $todayCount >= self::MAX_DAILY_OTPS;
    }

    /**
     * Invalidate previous OTPs
     */
    private function invalidatePreviousOtps(User $user, string $identifier, string $type, string $purpose): void
    {
        OtpVerification::where('user_id', $user->id)
            ->where('identifier', $identifier)
            ->where('type', $type)
            ->where('purpose', $purpose)
            ->where('is_used', false)
            ->update(['is_used' => true, 'used_at' => now()]);
    }

    /**
     * Get next resend time
     */
    private function getNextResendTime(User $user, string $type, string $purpose): ?Carbon
    {
        $lastOtp = OtpVerification::where('user_id', $user->id)
            ->where('type', $type)
            ->where('purpose', $purpose)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$lastOtp) {
            return null;
        }

        $nextResend = $lastOtp->created_at->addSeconds(self::RESEND_COOLDOWN_SECONDS);
        return $nextResend->isFuture() ? $nextResend : null;
    }

    /**
     * Clean up expired OTPs
     */
    public function cleanupExpiredOtps(): int
    {
        return OtpVerification::where('expires_at', '<', now())
            ->orWhere('created_at', '<', now()->subDays(7))
            ->delete();
    }
}