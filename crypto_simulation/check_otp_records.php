<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\OtpVerification;

echo "=== OTP Records Check ===\n\n";

// Check users without verification
$unverifiedUsers = User::whereNull('email_verified_at')->get();
echo "Users without email verification: " . $unverifiedUsers->count() . "\n";
foreach ($unverifiedUsers as $user) {
    echo "  - {$user->email} (ID: {$user->id})\n";
}
echo "\n";

// Check OTP records
$otpRecords = OtpVerification::orderBy('created_at', 'desc')->take(10)->get();
echo "Recent OTP records: " . $otpRecords->count() . "\n";
foreach ($otpRecords as $otp) {
    $expired = $otp->expires_at < now() ? 'EXPIRED' : 'VALID';
    $used = $otp->is_used ? 'USED' : 'UNUSED';
    echo "  - {$otp->identifier} | Code: {$otp->otp_code} | Purpose: {$otp->purpose} | {$expired} | {$used} | Attempts: {$otp->attempts}/3\n";
    echo "    Created: {$otp->created_at} | Expires: {$otp->expires_at}\n";
}

echo "\n=== Check Complete ===\n";
