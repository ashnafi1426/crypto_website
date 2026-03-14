<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class TwoFactorAuthService
{
    const RECOVERY_CODES_COUNT = 8;
    const TOTP_WINDOW = 30; // seconds
    const BACKUP_CODE_LENGTH = 8;

    /**
     * Generate 2FA secret for user
     */
    public function generateSecret(User $user): array
    {
        try {
            // Generate a random secret (32 characters base32)
            $secret = $this->generateBase32Secret();
            
            // Store encrypted secret
            $user->update([
                'two_factor_secret' => Crypt::encryptString($secret),
                'two_factor_enabled' => false, // Not enabled until confirmed
                'two_factor_confirmed_at' => null
            ]);

            // Generate QR code data
            $qrCodeUrl = $this->generateQrCodeUrl($user, $secret);

            return [
                'success' => true,
                'secret' => $secret,
                'qr_code_url' => $qrCodeUrl,
                'manual_entry_key' => $this->formatSecretForManualEntry($secret)
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to generate 2FA secret: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Confirm and enable 2FA
     */
    public function confirmTwoFactor(User $user, string $code): array
    {
        try {
            if (!$user->two_factor_secret) {
                return [
                    'success' => false,
                    'message' => '2FA secret not found. Please generate a new secret.'
                ];
            }

            // Verify the provided code
            if (!$this->verifyCode($user, $code)) {
                return [
                    'success' => false,
                    'message' => 'Invalid verification code'
                ];
            }

            // Generate recovery codes
            $recoveryCodes = $this->generateRecoveryCodes();

            // Enable 2FA
            $user->update([
                'two_factor_enabled' => true,
                'two_factor_confirmed_at' => now(),
                'two_factor_recovery_codes' => Crypt::encryptString(json_encode($recoveryCodes))
            ]);

            return [
                'success' => true,
                'message' => '2FA enabled successfully',
                'recovery_codes' => $recoveryCodes
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to confirm 2FA: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify 2FA code
     */
    public function verifyCode(User $user, string $code): bool
    {
        try {
            if (!$user->two_factor_secret) {
                return false;
            }

            $secret = Crypt::decryptString($user->two_factor_secret);
            
            // Check if it's a recovery code
            if ($this->isRecoveryCode($user, $code)) {
                $this->useRecoveryCode($user, $code);
                return true;
            }

            // Verify TOTP code
            return $this->verifyTotpCode($secret, $code);

        } catch (\Exception $e) {
            \Log::error('2FA verification error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Disable 2FA
     */
    public function disableTwoFactor(User $user): array
    {
        try {
            $user->update([
                'two_factor_enabled' => false,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null
            ]);

            return [
                'success' => true,
                'message' => '2FA disabled successfully'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to disable 2FA: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate new recovery codes
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        try {
            if (!$user->two_factor_enabled) {
                return [
                    'success' => false,
                    'message' => '2FA is not enabled'
                ];
            }

            $recoveryCodes = $this->generateRecoveryCodes();
            
            $user->update([
                'two_factor_recovery_codes' => Crypt::encryptString(json_encode($recoveryCodes))
            ]);

            return [
                'success' => true,
                'recovery_codes' => $recoveryCodes
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to regenerate recovery codes: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get 2FA status
     */
    public function getTwoFactorStatus(User $user): array
    {
        return [
            'enabled' => $user->two_factor_enabled,
            'confirmed' => !is_null($user->two_factor_confirmed_at),
            'method' => $user->two_factor_method,
            'confirmed_at' => $user->two_factor_confirmed_at,
            'has_recovery_codes' => !is_null($user->two_factor_recovery_codes)
        ];
    }

    /**
     * Generate base32 secret
     */
    private function generateBase32Secret(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 32; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Generate QR code URL
     */
    private function generateQrCodeUrl(User $user, string $secret): string
    {
        $appName = config('app.name', 'Crypto Exchange');
        $userEmail = urlencode($user->email);
        
        return "otpauth://totp/{$appName}:{$userEmail}?secret={$secret}&issuer=" . urlencode($appName);
    }

    /**
     * Format secret for manual entry
     */
    private function formatSecretForManualEntry(string $secret): string
    {
        return implode(' ', str_split($secret, 4));
    }

    /**
     * Generate recovery codes
     */
    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODES_COUNT; $i++) {
            $codes[] = strtoupper(Str::random(self::BACKUP_CODE_LENGTH));
        }
        return $codes;
    }

    /**
     * Verify TOTP code (simplified implementation)
     */
    private function verifyTotpCode(string $secret, string $code): bool
    {
        // This is a simplified TOTP implementation
        // In production, use a proper TOTP library like pragmarx/google2fa
        
        $timeSlice = floor(time() / self::TOTP_WINDOW);
        
        // Check current time slice and adjacent ones for clock drift
        for ($i = -1; $i <= 1; $i++) {
            $calculatedCode = $this->generateTotpCode($secret, $timeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Generate TOTP code (simplified)
     */
    private function generateTotpCode(string $secret, int $timeSlice): string
    {
        // Simplified TOTP generation - use proper library in production
        $hash = hash_hmac('sha1', pack('N*', 0, $timeSlice), base32_decode($secret), true);
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Check if code is a recovery code
     */
    private function isRecoveryCode(User $user, string $code): bool
    {
        if (!$user->two_factor_recovery_codes) {
            return false;
        }

        try {
            $recoveryCodes = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true);
            return in_array(strtoupper($code), $recoveryCodes);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Use recovery code (remove it from available codes)
     */
    private function useRecoveryCode(User $user, string $code): void
    {
        try {
            $recoveryCodes = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true);
            $recoveryCodes = array_diff($recoveryCodes, [strtoupper($code)]);
            
            $user->update([
                'two_factor_recovery_codes' => Crypt::encryptString(json_encode(array_values($recoveryCodes)))
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to use recovery code: ' . $e->getMessage());
        }
    }
}

// Helper function for base32 decoding
if (!function_exists('base32_decode')) {
    function base32_decode($input) {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $v = 0;
        $vbits = 0;
        
        for ($i = 0, $j = strlen($input); $i < $j; $i++) {
            $v <<= 5;
            $v += strpos($alphabet, $input[$i]);
            $vbits += 5;
            
            if ($vbits >= 8) {
                $output .= chr($v >> ($vbits - 8));
                $vbits -= 8;
            }
        }
        
        return $output;
    }
}