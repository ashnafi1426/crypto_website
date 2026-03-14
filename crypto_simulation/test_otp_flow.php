<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\OtpVerification;
use App\Services\OtpVerificationService;

echo "=== OTP Flow Test ===\n\n";

// Find a user without email verification
$user = User::whereNull('email_verified_at')->first();

if (!$user) {
    echo "Creating test user...\n";
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('Test@123456'),
        'email_verified_at' => null
    ]);
}

echo "User: {$user->email}\n";
echo "Email Verified: " . ($user->email_verified_at ? 'Yes' : 'No') . "\n\n";

// Check existing OTPs
$existingOtps = OtpVerification::where('identifier', $user->email)
    ->where('type', 'email')
    ->where('purpose', 'email_verification')
    ->orderBy('created_at', 'desc')
    ->get();

echo "Existing OTPs: " . $existingOtps->count() . "\n";
foreach ($existingOtps as $otp) {
    echo "  - Code: {$otp->otp_code}, Expires: {$otp->expires_at}, Used: " . ($otp->is_used ? 'Yes' : 'No') . ", Attempts: {$otp->attempts}\n";
}
echo "\n";

// Generate new OTP
echo "Generating new OTP...\n";
$otpService = app(OtpVerificationService::class);

try {
    $result = $otpService->generateOtp(
        $user,
        $user->email,
        'email',
        'email_verification',
        request()
    );
    
    if ($result['success']) {
        echo "✓ OTP Generated Successfully!\n";
        if (isset($result['otp_code'])) {
            echo "  OTP Code: {$result['otp_code']}\n";
        }
        echo "  Message: {$result['message']}\n\n";
        
        // Get the latest OTP
        $latestOtp = OtpVerification::where('identifier', $user->email)
            ->where('type', 'email')
            ->where('purpose', 'email_verification')
            ->where('is_used', false)
            ->orderBy('created_at', 'desc')
            ->first();
            
        if ($latestOtp) {
            echo "Latest OTP Details:\n";
            echo "  Code: {$latestOtp->otp_code}\n";
            echo "  Expires: {$latestOtp->expires_at}\n";
            echo "  Created: {$latestOtp->created_at}\n";
            echo "  Is Expired: " . ($latestOtp->expires_at < now() ? 'Yes' : 'No') . "\n\n";
            
            // Test verification
            echo "Testing OTP verification...\n";
            $verifyResult = $otpService->verifyOtp(
                $user,
                $user->email,
                $latestOtp->otp_code,
                'email',
                'email_verification'
            );
            
            if ($verifyResult['success']) {
                echo "✓ OTP Verified Successfully!\n";
                
                // Update user email_verified_at
                $user->email_verified_at = now();
                $user->save();
                
                echo "✓ User email verified!\n";
            } else {
                echo "✗ OTP Verification Failed: {$verifyResult['message']}\n";
            }
        }
    } else {
        echo "✗ Failed to generate OTP: {$result['message']}\n";
    }
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
