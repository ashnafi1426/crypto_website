<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo "=== Email Login Test ===\n\n";

// Get or create a test user
$email = 'test@example.com';
$password = 'Test@123456';

$user = User::where('email', $email)->first();

if (!$user) {
    echo "Creating test user...\n";
    $user = User::create([
        'name' => 'Test User',
        'email' => $email,
        'password' => Hash::make($password),
        'email_verified_at' => now(), // Verify email
        'locked_until' => null,
        'failed_login_attempts' => 0
    ]);
    echo "✅ Test user created\n\n";
} else {
    echo "Test user exists\n";
    echo "  Email: {$user->email}\n";
    echo "  Verified: " . ($user->email_verified_at ? 'Yes' : 'No') . "\n";
    echo "  Locked: " . ($user->locked_until && $user->locked_until > now() ? 'Yes' : 'No') . "\n";
    echo "  Failed attempts: {$user->failed_login_attempts}\n\n";
    
    // Reset if locked
    if ($user->locked_until && $user->locked_until > now()) {
        $user->locked_until = null;
        $user->failed_login_attempts = 0;
        $user->save();
        echo "✅ Unlocked account\n\n";
    }
    
    // Verify email if not verified
    if (!$user->email_verified_at) {
        $user->email_verified_at = now();
        $user->save();
        echo "✅ Email verified\n\n";
    }
    
    // Update password to known value
    $user->password = Hash::make($password);
    $user->save();
    echo "✅ Password reset to: {$password}\n\n";
}

echo "=== Test Credentials ===\n";
echo "Email: {$email}\n";
echo "Password: {$password}\n\n";

// Test password verification
echo "=== Password Verification Test ===\n";
if (Hash::check($password, $user->password)) {
    echo "✅ Password verification: PASS\n";
} else {
    echo "❌ Password verification: FAIL\n";
}

echo "\n=== Ready to Test ===\n";
echo "You can now login with:\n";
echo "  Email: {$email}\n";
echo "  Password: {$password}\n";
