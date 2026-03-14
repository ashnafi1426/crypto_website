<?php

/**
 * Generate Test OAuth Callback URL
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "=== Generate Test OAuth Callback URL ===\n\n";

// Find your actual OAuth user
$user = User::where('email', 'ashenafi14264@gmail.com')->first();

if (!$user) {
    echo "❌ User not found: ashenafi14264@gmail.com\n";
    echo "Available OAuth users:\n";
    $oauthUsers = User::whereIn('provider', ['google', 'apple'])->get();
    foreach ($oauthUsers as $oauthUser) {
        echo "   - {$oauthUser->name} ({$oauthUser->email})\n";
    }
    exit(1);
}

echo "Found user: {$user->name} ({$user->email})\n";
echo "Admin: " . ($user->is_admin ? 'Yes' : 'No') . "\n";
echo "Email verified: " . ($user->email_verified_at ? 'Yes' : 'No') . "\n\n";

// Generate a real token
$token = $user->createToken('oauth-callback-test')->plainTextToken;
echo "Generated token: " . substr($token, 0, 50) . "...\n\n";

// Create callback URLs for both regular and admin users
$userData = [
    'id' => $user->id,
    'name' => $user->name,
    'email' => $user->email,
    'avatar' => $user->avatar,
    'is_admin' => $user->is_admin,
];

$callbackUrl = config('services.frontend.url') . '/login?' . http_build_query([
    'auth_success' => 'true',
    'token' => $token,
    'user' => base64_encode(json_encode($userData)),
]);

echo "=== TEST CALLBACK URL ===\n";
echo $callbackUrl . "\n\n";

echo "=== INSTRUCTIONS ===\n";
echo "1. Copy the URL above\n";
echo "2. Paste it in your browser\n";
echo "3. You should be redirected to:\n";
if ($user->is_admin) {
    echo "   - Admin Panel (/admin) - because you are an admin\n";
} else {
    echo "   - Dashboard (/dashboard) - because you are a regular user\n";
}
echo "\n";

echo "4. Alternative test URLs:\n";
echo "   - Test Dashboard: http://localhost:5175/test-dashboard\n";
echo "   - Debug Tool: http://localhost:5175/debug_oauth_dashboard.html\n\n";

echo "5. If you want to test admin access:\n";
echo "   Run: php make_oauth_user_admin.php {$user->email}\n\n";

// Also create a test URL for the simple test dashboard
$testDashboardUrl = config('services.frontend.url') . '/test-dashboard?' . http_build_query([
    'auth_success' => 'true',
    'token' => $token,
    'user' => base64_encode(json_encode($userData)),
]);

echo "=== SIMPLE TEST DASHBOARD URL ===\n";
echo $testDashboardUrl . "\n\n";

echo "This URL will take you directly to a simple test dashboard that shows:\n";
echo "- Your authentication status\n";
echo "- Your user information\n";
echo "- Confirmation that OAuth is working\n\n";

echo "✅ Test URLs generated successfully!\n";
echo "The token will be valid for testing. Clean up with:\n";
echo "php -r \"require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap(); App\\Models\\User::find({$user->id})->tokens()->where('name', 'oauth-callback-test')->delete(); echo 'Token cleaned up\\n';\"\n";