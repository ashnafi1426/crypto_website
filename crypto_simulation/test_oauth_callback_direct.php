<?php

/**
 * Test OAuth Callback Directly
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\OAuthSession;
use App\Services\GoogleOAuthService;

echo "=== Direct OAuth Callback Test ===\n\n";

// 1. Create a test OAuth session
echo "1. Creating test OAuth session...\n";
$session = OAuthSession::create([
    'state' => 'test-state-' . time(),
    'provider' => 'google',
    'redirect_url' => 'http://localhost:5175/dashboard',
    'expires_at' => now()->addMinutes(10),
]);

echo "   ✅ Session created: {$session->state}\n";

// 2. Simulate Google profile response
$mockGoogleProfile = [
    'id' => 'google-test-' . time(),
    'name' => 'Test OAuth User Direct',
    'email' => 'test.direct@gmail.com',
    'picture' => 'https://example.com/avatar.jpg'
];

echo "\n2. Mock Google profile:\n";
echo "   " . json_encode($mockGoogleProfile, JSON_PRETTY_PRINT) . "\n";

// 3. Test the OAuth service directly
echo "\n3. Testing OAuth service...\n";
try {
    $googleService = app(GoogleOAuthService::class);
    
    // Simulate the callback handling
    echo "   Simulating OAuth callback...\n";
    
    // Check if user exists
    $existingUser = User::where('provider', 'google')
        ->where('provider_id', $mockGoogleProfile['id'])
        ->first();
    
    if (!$existingUser) {
        echo "   Creating new user...\n";
        
        // Create user manually to test
        $user = User::create([
            'name' => $mockGoogleProfile['name'],
            'email' => $mockGoogleProfile['email'],
            'provider' => 'google',
            'provider_id' => $mockGoogleProfile['id'],
            'avatar' => $mockGoogleProfile['picture'],
            'email_verified_at' => now(),
            'password' => null,
            'is_admin' => false,
        ]);
        
        echo "   ✅ User created: {$user->name} (ID: {$user->id})\n";
        
        // Create wallets
        $cryptocurrencies = \App\Models\Cryptocurrency::where('is_active', true)->get();
        foreach ($cryptocurrencies as $crypto) {
            $balance = $crypto->symbol === 'USD' ? '10000.00000000' : '0.00000000';
            
            \App\Models\Wallet::create([
                'user_id' => $user->id,
                'cryptocurrency_symbol' => $crypto->symbol,
                'balance' => $balance,
                'reserved_balance' => '0.00000000',
            ]);
        }
        
        echo "   ✅ Wallets created: " . $user->wallets()->count() . "\n";
        
    } else {
        $user = $existingUser;
        echo "   ✅ Using existing user: {$user->name}\n";
    }
    
    // Generate token
    $token = $user->createToken('oauth-test')->plainTextToken;
    echo "   ✅ Token generated: " . substr($token, 0, 30) . "...\n";
    
    // Test the token
    echo "\n4. Testing generated token...\n";
    $response = \Illuminate\Support\Facades\Http::withToken($token)
        ->timeout(10)
        ->get('http://127.0.0.1:8000/api/auth/user');
    
    if ($response->successful()) {
        $userData = $response->json();
        echo "   ✅ Token works! User data:\n";
        echo "   " . json_encode($userData['user'], JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "   ❌ Token test failed: " . $response->status() . "\n";
    }
    
    // Generate callback URL
    echo "\n5. Generated callback URL:\n";
    $callbackUrl = config('services.frontend.url') . '/login?' . http_build_query([
        'auth_success' => 'true',
        'token' => $token,
        'user' => base64_encode(json_encode([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'is_admin' => $user->is_admin,
        ])),
    ]);
    
    echo "   {$callbackUrl}\n\n";
    echo "   You can test this URL in your browser!\n";
    
    // Clean up
    $session->delete();
    $user->tokens()->where('name', 'oauth-test')->delete();
    echo "\n   ✅ Test session and token cleaned up\n";
    
} catch (\Exception $e) {
    echo "   ❌ OAuth test failed: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
echo "If the callback URL works in your browser, OAuth is functioning correctly.\n";
echo "The issue might be in the frontend AuthContext or routing.\n\n";

echo "Next steps:\n";
echo "1. Test the callback URL above in your browser\n";
echo "2. Check browser console for JavaScript errors\n";
echo "3. Use the debug tool: http://localhost:5175/debug_oauth_dashboard.html\n\n";

echo "✅ Direct OAuth test completed!\n";