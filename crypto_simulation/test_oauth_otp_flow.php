<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Testing OAuth OTP Flow ===\n\n";

// Test 1: Check if user 23 needs OTP
echo "1. Checking user 23 (ashenafi14262@gmail.com):\n";
$user23 = App\Models\User::find(23);
if ($user23) {
    echo "   - Email: {$user23->email}\n";
    echo "   - Email Verified: " . ($user23->email_verified_at ? 'YES' : 'NO') . "\n";
    echo "   - Provider: {$user23->provider}\n";
    echo "   - Requires OTP: " . ($user23->email_verified_at ? 'NO' : 'YES') . "\n";
} else {
    echo "   - User not found\n";
}

echo "\n";

// Test 2: Check if user 33 needs OTP
echo "2. Checking user 33 (ashenafi14264@gmail.com):\n";
$user33 = App\Models\User::find(33);
if ($user33) {
    echo "   - Email: {$user33->email}\n";
    echo "   - Email Verified: " . ($user33->email_verified_at ? 'YES' : 'NO') . "\n";
    echo "   - Provider: {$user33->provider}\n";
    echo "   - Requires OTP: " . ($user33->email_verified_at ? 'NO' : 'YES') . "\n";
} else {
    echo "   - User not found\n";
}

echo "\n";

// Test 3: Check recent OTP verifications
echo "3. Checking recent OTP verifications:\n";
$otps = App\Models\OtpVerification::whereIn('user_id', [23, 33])
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get(['user_id', 'identifier', 'type', 'purpose', 'is_used', 'created_at']);

if ($otps->count() > 0) {
    foreach ($otps as $otp) {
        echo "   - User {$otp->user_id}: {$otp->identifier} ({$otp->type}/{$otp->purpose}) - ";
        echo ($otp->is_used ? 'USED' : 'PENDING') . " - {$otp->created_at}\n";
    }
} else {
    echo "   - No OTP records found\n";
}

echo "\n";

// Test 4: Simulate OAuth callback response
echo "4. Simulating OAuth callback response:\n";
if ($user23) {
    $requiresOtp = !$user23->email_verified_at;
    echo "   - requires_otp parameter: " . ($requiresOtp ? 'true' : 'false') . "\n";
    echo "   - Redirect URL should include: requires_otp=true\n";
    
    $redirectUrl = 'http://localhost:5173/login';
    $params = [
        'auth_success' => 'true',
        'token' => 'sample_token_here',
        'requires_otp' => $requiresOtp ? 'true' : 'false',
        'user' => base64_encode(json_encode([
            'id' => $user23->id,
            'name' => $user23->name,
            'email' => $user23->email,
            'email_verified' => false,
        ])),
    ];
    
    $fullUrl = $redirectUrl . '?' . http_build_query($params);
    echo "\n   Full redirect URL:\n   {$fullUrl}\n";
}

echo "\n=== Test Complete ===\n";
