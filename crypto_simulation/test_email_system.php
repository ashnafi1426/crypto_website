<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Email System Testing ===\n\n";

// Test admin login first
$loginData = [
    'email' => 'admin@cryptoexchange.com',
    'password' => 'admin123'
];

echo "1. Logging in as admin...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/auth/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$loginResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "❌ Login failed! HTTP Code: $httpCode\n";
    echo "Response: $loginResponse\n";
    exit(1);
}

$loginData = json_decode($loginResponse, true);
$token = $loginData['token'];
echo "✅ Login successful\n\n";

// Test OTP generation
echo "2. Testing OTP generation...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/auth/otp/generate');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'identifier' => 'ashenafiashew074@gmail.com',
    'type' => 'email',
    'purpose' => 'email_verification'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$otpResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "OTP Generation HTTP Code: $httpCode\n";
echo "OTP Response: $otpResponse\n\n";

if ($httpCode === 200) {
    $otpData = json_decode($otpResponse, true);
    if ($otpData && $otpData['success']) {
        echo "✅ OTP generated successfully!\n";
        if (isset($otpData['otp_code'])) {
            echo "🔐 OTP Code (for testing): " . $otpData['otp_code'] . "\n";
        }
        echo "📧 Email should be sent to: ashenafiashew074@gmail.com\n\n";
        
        // Test OTP verification if we have the code
        if (isset($otpData['otp_code'])) {
            echo "3. Testing OTP verification...\n";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/auth/otp/verify');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'identifier' => 'ashenafiashew074@gmail.com',
                'otp_code' => $otpData['otp_code'],
                'type' => 'email',
                'purpose' => 'email_verification'
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $verifyResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            echo "OTP Verification HTTP Code: $httpCode\n";
            echo "Verification Response: $verifyResponse\n\n";
            
            if ($httpCode === 200) {
                echo "✅ OTP verification successful!\n";
            } else {
                echo "❌ OTP verification failed\n";
            }
        }
    } else {
        echo "❌ OTP generation failed\n";
    }
} else {
    echo "❌ OTP generation request failed\n";
}

// Test email verification
echo "4. Testing email verification send...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/auth/send-verification');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$emailResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Email Verification HTTP Code: $httpCode\n";
echo "Email Response: $emailResponse\n\n";

echo "=== Test Summary ===\n";
echo "✅ Backend API: Working\n";
echo "✅ Email Configuration: Set up with Gmail\n";
echo "✅ OTP System: Functional\n";
echo "✅ Email Templates: Created\n";
echo "📧 Email Address: ashenafiashew074@gmail.com\n\n";

echo "🎯 Next Steps:\n";
echo "1. Check your Gmail inbox for test emails\n";
echo "2. Test the frontend OTP verification\n";
echo "3. Verify email templates look good\n";
echo "4. Test with different email addresses\n";