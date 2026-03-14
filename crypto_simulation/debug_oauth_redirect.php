<?php

/**
 * Debug OAuth Redirect Issue
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\OAuthSession;
use Illuminate\Support\Facades\Http;

echo "=== OAuth Redirect Debug ===\n\n";

// 1. Check recent OAuth sessions
echo "1. Recent OAuth Sessions (last 1 hour):\n";
$recentSessions = OAuthSession::where('created_at', '>', now()->subHour())->orderBy('created_at', 'desc')->get();

if ($recentSessions->count() > 0) {
    foreach ($recentSessions as $session) {
        echo "   - State: " . substr($session->state, 0, 20) . "...\n";
        echo "     Provider: {$session->provider}\n";
        echo "     Redirect URL: {$session->redirect_url}\n";
        echo "     Created: {$session->created_at}\n";
        echo "     Expires: {$session->expires_at}\n\n";
    }
} else {
    echo "   No recent OAuth sessions found.\n";
}

// 2. Check recent OAuth users
echo "2. Recent OAuth Users (last 1 hour):\n";
$recentUsers = User::whereIn('provider', ['google', 'apple'])
    ->where('created_at', '>', now()->subHour())
    ->orderBy('created_at', 'desc')
    ->get();

if ($recentUsers->count() > 0) {
    foreach ($recentUsers as $user) {
        echo "   - {$user->name} ({$user->email})\n";
        echo "     Provider: {$user->provider}\n";
        echo "     Admin: " . ($user->is_admin ? 'Yes' : 'No') . "\n";
        echo "     Created: {$user->created_at}\n";
        echo "     Tokens: " . $user->tokens()->count() . "\n\n";
    }
} else {
    echo "   No recent OAuth users found.\n";
}

// 3. Test OAuth callback simulation
echo "3. Simulating OAuth Callback:\n";

// Create a test OAuth session
$testSession = OAuthSession::create([
    'state' => 'test-state-' . time(),
    'provider' => 'google',
    'redirect_url' => 'http://localhost:5175/dashboard',
    'expires_at' => now()->addMinutes(10),
]);

echo "   Created test session: {$testSession->state}\n";

// Simulate Google profile data
$testProfile = [
    'id' => 'test-google-id-' . time(),
    'name' => 'Test OAuth User',
    'email' => 'test.oauth.debug@gmail.com',
    'picture' => 'https://example.com/avatar.jpg'
];

echo "   Simulating Google profile:\n";
echo "   " . json_encode($testProfile, JSON_PRETTY_PRINT) . "\n";

try {
    $googleService = app(\App\Services\GoogleOAuthService::class);
    
    // Simulate the callback process
    echo "\n   Testing user creation process...\n";
    
    // Check if user exists
    $existingUser = User::where('provider', 'google')
        ->where('provider_id', $testProfile['id'])
        ->first();
    
    if ($existingUser) {
        echo "   User already exists: {$existingUser->name}\n";
        $user = $existingUser;
    } else {
        echo "   Creating new user...\n";
        
        // Create user manually to test
        $user = User::create([
            'name' => $testProfile['name'],
            'email' => $testProfile['email'],
            'provider' => 'google',
            'provider_id' => $testProfile['id'],
            'avatar' => $testProfile['picture'] ?? null,
            'email_verified_at' => now(),
            'password' => null,
            'is_admin' => false,
        ]);
        
        echo "   ✅ User created: {$user->name} (ID: {$user->id})\n";
        
        // Initialize wallets
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
        
        echo "   ✅ Wallets initialized: " . $user->wallets()->count() . "\n";
    }
    
    // Generate token
    $token = $user->createToken('google-oauth')->plainTextToken;
    echo "   ✅ Token generated: " . substr($token, 0, 20) . "...\n";
    
    // Simulate redirect URL generation
    $redirectUrl = $testSession->redirect_url ?: config('services.frontend.url') . '/dashboard';
    $callbackUrl = $redirectUrl . '?' . http_build_query([
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
    
    echo "\n   Generated callback URL:\n";
    echo "   " . $callbackUrl . "\n";
    
    // Test URL parsing
    $parsedUrl = parse_url($callbackUrl);
    parse_str($parsedUrl['query'], $queryParams);
    
    echo "\n   Parsed callback parameters:\n";
    echo "   - auth_success: " . ($queryParams['auth_success'] ?? 'missing') . "\n";
    echo "   - token: " . (isset($queryParams['token']) ? 'present' : 'missing') . "\n";
    echo "   - user: " . (isset($queryParams['user']) ? 'present' : 'missing') . "\n";
    
    if (isset($queryParams['user'])) {
        $userData = json_decode(base64_decode($queryParams['user']), true);
        echo "   - decoded user data:\n";
        echo "     " . json_encode($userData, JSON_PRETTY_PRINT) . "\n";
    }
    
    // Clean up test data
    $testSession->delete();
    if (!$existingUser) {
        $user->wallets()->delete();
        $user->tokens()->delete();
        $user->delete();
        echo "\n   ✅ Test data cleaned up\n";
    }
    
} catch (\Exception $e) {
    echo "   ❌ Error during simulation: " . $e->getMessage() . "\n";
    $testSession->delete();
}

// 4. Check frontend configuration
echo "\n4. Frontend Configuration Check:\n";
echo "   Frontend URL: " . config('services.frontend.url') . "\n";
echo "   Google Client ID: " . (config('services.google.client_id') ? 'Set' : 'Not set') . "\n";
echo "   Google Redirect URI: " . config('services.google.redirect_uri') . "\n";

// 5. Test actual OAuth endpoints
echo "\n5. Testing OAuth Endpoints:\n";

// Test Google OAuth redirect
echo "   Testing Google OAuth redirect...\n";
try {
    $response = Http::get('http://127.0.0.1:8000/api/auth/google?redirect_url=http://localhost:5175/dashboard');
    if ($response->successful()) {
        $data = $response->json();
        if ($data['success']) {
            echo "   ✅ Google OAuth redirect endpoint working\n";
            echo "   Generated auth URL: " . (strlen($data['auth_url']) > 100 ? 'Yes' : 'No') . "\n";
            
            // Clean up session
            if (isset($data['state'])) {
                $session = OAuthSession::where('state', $data['state'])->first();
                if ($session) {
                    $session->delete();
                }
            }
        } else {
            echo "   ❌ Google OAuth redirect failed: " . ($data['message'] ?? 'Unknown error') . "\n";
        }
    } else {
        echo "   ❌ Google OAuth redirect endpoint failed: " . $response->status() . "\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Google OAuth redirect error: " . $e->getMessage() . "\n";
}

// 6. Check Laravel logs for errors
echo "\n6. Checking for Recent Errors:\n";
$logPath = storage_path('logs/laravel.log');
if (file_exists($logPath)) {
    $logContent = file_get_contents($logPath);
    $lines = explode("\n", $logContent);
    $recentLines = array_slice($lines, -50); // Last 50 lines
    
    $errorFound = false;
    foreach ($recentLines as $line) {
        if (strpos($line, 'ERROR') !== false || strpos($line, 'OAuth') !== false) {
            echo "   " . $line . "\n";
            $errorFound = true;
        }
    }
    
    if (!$errorFound) {
        echo "   No recent OAuth-related errors found in logs\n";
    }
} else {
    echo "   Log file not found: {$logPath}\n";
}

echo "\n=== Debug Summary ===\n";
echo "1. Check browser console for JavaScript errors\n";
echo "2. Verify both servers are running (backend:8000, frontend:5175)\n";
echo "3. Ensure Google OAuth redirect URI is configured correctly\n";
echo "4. Check network tab in browser dev tools during OAuth flow\n";
echo "5. Look for any CORS or authentication errors\n\n";

echo "✅ Debug analysis complete!\n";