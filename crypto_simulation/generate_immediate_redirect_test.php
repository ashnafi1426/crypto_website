<?php

/**
 * Generate Test URL for Immediate Redirect (No Refresh Required)
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "=== Generate Immediate Redirect Test URL ===\n\n";

// Find your user
$user = User::where('email', 'ashenafi14264@gmail.com')->first();

if (!$user) {
    echo "❌ User not found\n";
    exit(1);
}

// Generate a fresh token
$token = $user->createToken('immediate-redirect-test')->plainTextToken;

echo "User: {$user->name} ({$user->email})\n";
echo "Admin: " . ($user->is_admin ? 'Yes' : 'No') . "\n";
echo "Token: " . substr($token, 0, 40) . "...\n\n";

// Create user data for callback
$userData = [
    'id' => $user->id,
    'name' => $user->name,
    'email' => $user->email,
    'avatar' => $user->avatar,
    'is_admin' => $user->is_admin,
];

// Generate callback URL
$callbackUrl = config('services.frontend.url') . '/login?' . http_build_query([
    'auth_success' => 'true',
    'token' => $token,
    'user' => base64_encode(json_encode($userData)),
]);

echo "=== IMMEDIATE REDIRECT TEST URL ===\n";
echo $callbackUrl . "\n\n";

echo "=== TESTING INSTRUCTIONS ===\n";
echo "1. Copy the URL above\n";
echo "2. Paste it in your browser\n";
echo "3. You should be redirected to dashboard IMMEDIATELY (no refresh needed)\n";
echo "4. The page should not stay on /login - it should redirect automatically\n\n";

echo "Expected behavior:\n";
if ($user->is_admin) {
    echo "   - Should redirect to: /admin (because you are admin)\n";
} else {
    echo "   - Should redirect to: /dashboard (because you are regular user)\n";
}
echo "   - No page refresh required\n";
echo "   - Immediate redirection after processing OAuth callback\n\n";

echo "If it still requires refresh:\n";
echo "   - Check browser console for JavaScript errors\n";
echo "   - Verify React Router is working correctly\n";
echo "   - Check AuthContext state updates\n\n";

// Also test making user admin if needed
echo "To test admin redirection:\n";
echo "   Run: php make_oauth_user_admin.php {$user->email}\n";
echo "   Then test the URL again\n\n";

echo "✅ Immediate redirect test URL generated!\n";
echo "This should work without any page refresh.\n";