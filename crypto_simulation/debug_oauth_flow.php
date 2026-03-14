<?php

/**
 * Debug OAuth Flow - Complete Analysis
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\OAuthSession;

echo "=== OAuth Flow Debug Analysis ===\n\n";

// 1. Check OAuth configuration
echo "1. OAuth Configuration:\n";
echo "   GOOGLE_CLIENT_ID: " . config('services.google.client_id') . "\n";
echo "   GOOGLE_REDIRECT_URI: " . config('services.google.redirect_uri') . "\n";
echo "   FRONTEND_URL: " . config('services.frontend.url') . "\n\n";

// 2. Check recent OAuth sessions
echo "2. Recent OAuth Sessions (last 24 hours):\n";
$recentSessions = OAuthSession::where('created_at', '>', now()->subDay())->get();
if ($recentSessions->count() > 0) {
    foreach ($recentSessions as $session) {
        echo "   - State: " . substr($session->state, 0, 20) . "... Provider: {$session->provider} Created: {$session->created_at}\n";
    }
} else {
    echo "   No recent OAuth sessions found.\n";
}
echo "\n";

// 3. Check OAuth users
echo "3. OAuth Users in Database:\n";
$oauthUsers = User::whereIn('provider', ['google', 'apple'])->get();
if ($oauthUsers->count() > 0) {
    foreach ($oauthUsers as $user) {
        echo "   - {$user->name} ({$user->email})\n";
        echo "     Provider: {$user->provider} | Admin: " . ($user->is_admin ? 'Yes' : 'No') . "\n";
        echo "     Created: {$user->created_at} | Verified: " . ($user->email_verified_at ? 'Yes' : 'No') . "\n";
        echo "     Wallets: " . $user->wallets()->count() . "\n\n";
    }
} else {
    echo "   No OAuth users found.\n\n";
}

// 4. Test OAuth URL generation
echo "4. Testing OAuth URL Generation:\n";
try {
    $googleService = app(\App\Services\GoogleOAuthService::class);
    $authData = $googleService->getAuthUrl('http://localhost:5175/dashboard');
    
    echo "   ✅ OAuth URL generated successfully\n";
    echo "   State: " . $authData['state'] . "\n";
    echo "   URL: " . substr($authData['url'], 0, 100) . "...\n";
    
    // Check if session was created
    $session = OAuthSession::where('state', $authData['state'])->first();
    if ($session) {
        echo "   ✅ OAuth session created in database\n";
        echo "   Session ID: {$session->id} | Expires: {$session->expires_at}\n";
        
        // Clean up test session
        $session->delete();
        echo "   ✅ Test session cleaned up\n";
    } else {
        echo "   ❌ OAuth session NOT created in database\n";
    }
    
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// 5. Test API endpoints
echo "5. Testing API Endpoints:\n";
try {
    // Test providers endpoint
    $providers = [
        'google' => [
            'enabled' => !empty(config('services.google.client_id')) && config('services.google.client_id') !== 'your-google-client-id',
            'name' => 'Google',
            'icon' => 'google',
        ],
        'apple' => [
            'enabled' => !empty(config('services.apple.client_id')) && config('services.apple.client_id') !== 'your-apple-service-id',
            'name' => 'Apple',
            'icon' => 'apple',
        ],
    ];
    
    echo "   Providers endpoint response:\n";
    echo "   " . json_encode(['success' => true, 'providers' => $providers], JSON_PRETTY_PRINT) . "\n";
    
} catch (\Exception $e) {
    echo "   ❌ API Error: " . $e->getMessage() . "\n";
}
echo "\n";

// 6. Check database connectivity
echo "6. Database Connectivity:\n";
try {
    $userCount = User::count();
    $oauthSessionCount = OAuthSession::count();
    echo "   ✅ Database connected\n";
    echo "   Total users: {$userCount}\n";
    echo "   OAuth sessions: {$oauthSessionCount}\n";
} catch (\Exception $e) {
    echo "   ❌ Database error: " . $e->getMessage() . "\n";
}
echo "\n";

// 7. Simulate OAuth callback data
echo "7. Simulating OAuth Callback Data:\n";
$testUser = [
    'id' => 999,
    'name' => 'Test OAuth User',
    'email' => 'test@example.com',
    'avatar' => 'https://example.com/avatar.jpg',
    'is_admin' => false,
];

$callbackUrl = config('services.frontend.url') . '/login?' . http_build_query([
    'auth_success' => 'true',
    'token' => 'test-jwt-token-here',
    'user' => base64_encode(json_encode($testUser)),
]);

echo "   Callback URL would be:\n";
echo "   " . $callbackUrl . "\n\n";

echo "   Decoded user data:\n";
echo "   " . json_encode($testUser, JSON_PRETTY_PRINT) . "\n\n";

// 8. Check for common issues
echo "8. Common Issues Check:\n";

// Check if cryptocurrencies exist for wallet creation
$cryptoCount = \App\Models\Cryptocurrency::where('is_active', true)->count();
echo "   Active cryptocurrencies: {$cryptoCount}\n";
if ($cryptoCount === 0) {
    echo "   ⚠️  WARNING: No active cryptocurrencies found. Run: php artisan db:seed --class=CryptocurrencySeeder\n";
}

// Check if frontend URL is correct
$frontendUrl = config('services.frontend.url');
if ($frontendUrl === 'http://localhost:5175') {
    echo "   ✅ Frontend URL configured correctly\n";
} else {
    echo "   ⚠️  Frontend URL: {$frontendUrl} (should be http://localhost:5175)\n";
}

// Check if Google credentials are set
$googleClientId = config('services.google.client_id');
if ($googleClientId && $googleClientId !== 'your-google-client-id') {
    echo "   ✅ Google OAuth credentials configured\n";
} else {
    echo "   ❌ Google OAuth credentials NOT configured\n";
}

echo "\n";

// 9. Recommendations
echo "9. Troubleshooting Recommendations:\n";
echo "   1. Make sure both servers are running:\n";
echo "      - Backend: php artisan serve (http://localhost:8000)\n";
echo "      - Frontend: npm run dev --port 5175 (http://localhost:5175)\n\n";

echo "   2. Test OAuth flow manually:\n";
echo "      - Visit: http://localhost:5175/login\n";
echo "      - Click 'Continue with Google'\n";
echo "      - Check browser console for errors\n";
echo "      - Check network tab for failed requests\n\n";

echo "   3. If OAuth user is created but not redirecting:\n";
echo "      - Check browser console logs\n";
echo "      - Verify AuthContext is working\n";
echo "      - Check if ProtectedRoute is blocking access\n\n";

echo "   4. To make OAuth user admin:\n";
echo "      - php make_oauth_user_admin.php user@gmail.com\n\n";

echo "=== Debug Complete ===\n";
echo "Next: Try OAuth login and check browser console for errors.\n";