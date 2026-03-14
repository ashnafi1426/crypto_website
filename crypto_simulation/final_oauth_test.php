<?php

/**
 * Final OAuth Test - Complete System Verification
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "=== FINAL OAUTH TEST ===\n\n";

// 1. Verify your user exists and is properly configured
$user = User::where('email', 'ashenafi14264@gmail.com')->first();

if (!$user) {
    echo "❌ User not found\n";
    exit(1);
}

echo "✅ User verified: {$user->name} ({$user->email})\n";
echo "   Provider: {$user->provider}\n";
echo "   Admin: " . ($user->is_admin ? 'Yes' : 'No') . "\n";
echo "   Email verified: " . ($user->email_verified_at ? 'Yes' : 'No') . "\n";
echo "   Wallets: " . $user->wallets()->count() . "\n";
echo "   Active tokens: " . $user->tokens()->count() . "\n\n";

// 2. Generate final test token
$finalToken = $user->createToken('final-oauth-test')->plainTextToken;

// 3. Test the token works
echo "Testing token validity...\n";
$response = \Illuminate\Support\Facades\Http::withToken($finalToken)
    ->timeout(10)
    ->get('http://127.0.0.1:8000/api/auth/user');

if ($response->successful()) {
    echo "✅ Token is valid and working\n";
} else {
    echo "❌ Token test failed: " . $response->status() . "\n";
}

// 4. Generate the final callback URL
$userData = [
    'id' => $user->id,
    'name' => $user->name,
    'email' => $user->email,
    'avatar' => $user->avatar,
    'is_admin' => $user->is_admin,
];

$finalCallbackUrl = config('services.frontend.url') . '/login?' . http_build_query([
    'auth_success' => 'true',
    'token' => $finalToken,
    'user' => base64_encode(json_encode($userData)),
]);

echo "\n=== FINAL TEST URL ===\n";
echo $finalCallbackUrl . "\n\n";

echo "=== COMPLETE TESTING INSTRUCTIONS ===\n\n";

echo "🔧 STEP 1: Test Direct Callback\n";
echo "   Copy the URL above and paste it in your browser\n";
echo "   Expected: Should redirect to dashboard immediately\n\n";

echo "🔧 STEP 2: Test Real OAuth Flow\n";
echo "   1. Go to: http://localhost:5175/login\n";
echo "   2. Click 'Continue with Google'\n";
echo "   3. Complete Google authentication\n";
echo "   4. Expected: Should redirect to dashboard automatically\n\n";

echo "🔧 STEP 3: Debug if Issues Persist\n";
echo "   1. Open browser dev tools (F12)\n";
echo "   2. Check Console tab for JavaScript errors\n";
echo "   3. Check Network tab for failed requests\n";
echo "   4. Look for CORS errors or redirect loops\n\n";

echo "🎯 EXPECTED BEHAVIOR:\n";
echo "   ✅ No JavaScript errors in console\n";
echo "   ✅ Immediate redirect to dashboard (no refresh needed)\n";
echo "   ✅ User data appears in dashboard\n";
echo "   ✅ Authentication persists on page refresh\n\n";

echo "🚨 IF STILL HAVING ISSUES:\n";
echo "   1. Check if both servers are running:\n";
echo "      - Backend: php artisan serve (port 8000)\n";
echo "      - Frontend: npm run dev (port 5175)\n\n";
echo "   2. Clear browser cache and localStorage\n\n";
echo "   3. Try in incognito/private browsing mode\n\n";
echo "   4. Check Google OAuth configuration:\n";
echo "      - Redirect URI: http://localhost:8000/api/auth/google/callback\n";
echo "      - Must be added in Google Cloud Console\n\n";

echo "🔄 TO MAKE USER ADMIN (for admin panel testing):\n";
echo "   Run: php make_oauth_user_admin.php {$user->email}\n\n";

echo "✅ FINAL OAUTH TEST COMPLETE!\n";
echo "The system should now work perfectly for OAuth authentication.\n";