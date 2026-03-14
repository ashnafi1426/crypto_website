<?php

/**
 * Debug Dashboard Access Issue
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\OAuthSession;
use Illuminate\Support\Facades\Http;

echo "=== Dashboard Access Debug ===\n\n";

// 1. Check recent OAuth sessions
echo "1. Recent OAuth Sessions (last 2 hours):\n";
$recentSessions = OAuthSession::where('created_at', '>', now()->subHours(2))->get();
if ($recentSessions->count() > 0) {
    foreach ($recentSessions as $session) {
        echo "   - State: " . substr($session->state, 0, 20) . "...\n";
        echo "     Provider: {$session->provider}\n";
        echo "     Created: {$session->created_at}\n";
        echo "     Expires: {$session->expires_at}\n";
        echo "     Redirect URL: " . ($session->redirect_url ?? 'None') . "\n\n";
    }
} else {
    echo "   No recent OAuth sessions found.\n\n";
}

// 2. Check OAuth users
echo "2. OAuth Users:\n";
$oauthUsers = User::whereIn('provider', ['google', 'apple'])->get();
if ($oauthUsers->count() > 0) {
    foreach ($oauthUsers as $user) {
        echo "   - {$user->name} ({$user->email})\n";
        echo "     Provider: {$user->provider}\n";
        echo "     Admin: " . ($user->is_admin ? 'Yes' : 'No') . "\n";
        echo "     Email Verified: " . ($user->email_verified_at ? 'Yes' : 'No') . "\n";
        echo "     Created: {$user->created_at}\n";
        echo "     Tokens: " . $user->tokens()->count() . "\n";
        echo "     Wallets: " . $user->wallets()->count() . "\n\n";
    }
} else {
    echo "   No OAuth users found.\n\n";
}

// 3. Test OAuth callback simulation
echo "3. Simulating OAuth Callback:\n";
try {
    $googleService = app(\App\Services\GoogleOAuthService::class);
    
    // Simulate Google profile data
    $mockProfile = [
        'id' => 'test-google-id-' . time(),
        'name' => 'Test OAuth User',
        'email' => 'test.oauth.debug@gmail.com',
        'picture' => 'https://example.com/avatar.jpg'
    ];
    
    echo "   Mock Google profile:\n";
    echo "   " . json_encode($mockProfile, JSON_PRETTY_PRINT) . "\n\n";
    
    // Test user creation logic
    $existingUser = User::where('provider', 'google')
        ->where('provider_id', $mockProfile['id'])
        ->first();
    
    if (!$existingUser) {
        echo "   ✅ User would be created (new OAuth user)\n";
        
        // Check if email exists with different provider
        $emailUser = User::where('email', $mockProfile['email'])->first();
        if ($emailUser) {
            echo "   ⚠️  Email exists with different provider: " . ($emailUser->provider ?? 'local') . "\n";
            echo "   User would be linked to existing account\n";
        } else {
            echo "   ✅ New user would be created\n";
        }
    } else {
        echo "   ✅ Existing user would be updated\n";
    }
    
} catch (\Exception $e) {
    echo "   ❌ OAuth simulation error: " . $e->getMessage() . "\n";
}

// 4. Test API endpoints
echo "\n4. Testing Critical API Endpoints:\n";

// Test OAuth providers endpoint
echo "   Testing /api/auth/providers...\n";
try {
    $response = Http::timeout(10)->get('http://127.0.0.1:8000/api/auth/providers');
    if ($response->successful()) {
        echo "   ✅ Providers endpoint working\n";
    } else {
        echo "   ❌ Providers endpoint failed: " . $response->status() . "\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Providers endpoint error: " . $e->getMessage() . "\n";
}

// Test Google OAuth redirect
echo "\n   Testing /api/auth/google...\n";
try {
    $response = Http::timeout(10)->get('http://127.0.0.1:8000/api/auth/google?redirect_url=http://localhost:5175/dashboard');
    if ($response->successful()) {
        $data = $response->json();
        if ($data['success'] && isset($data['auth_url'])) {
            echo "   ✅ Google OAuth redirect working\n";
            
            // Clean up test session
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
        echo "   ❌ Google OAuth redirect failed: " . $response->status() . "\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Google OAuth redirect error: " . $e->getMessage() . "\n";
}

// 5. Check frontend configuration
echo "\n5. Frontend Configuration Check:\n";
echo "   Frontend URL: " . config('services.frontend.url') . "\n";
echo "   Google Client ID: " . (config('services.google.client_id') ? 'Set' : 'Not set') . "\n";
echo "   Google Redirect URI: " . config('services.google.redirect_uri') . "\n";

// 6. Test user authentication flow
echo "\n6. Testing User Authentication Flow:\n";
$testUser = User::whereIn('provider', ['google', 'apple'])->first();
if ($testUser) {
    echo "   Using test user: {$testUser->name} ({$testUser->email})\n";
    
    // Create token
    $token = $testUser->createToken('debug-token')->plainTextToken;
    echo "   ✅ Token created: " . substr($token, 0, 20) . "...\n";
    
    // Test user endpoint
    try {
        $response = Http::withToken($token)->timeout(10)->get('http://127.0.0.1:8000/api/auth/user');
        if ($response->successful()) {
            $userData = $response->json();
            echo "   ✅ User endpoint working\n";
            echo "   User data: " . json_encode($userData['user'] ?? [], JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "   ❌ User endpoint failed: " . $response->status() . "\n";
        }
    } catch (\Exception $e) {
        echo "   ❌ User endpoint error: " . $e->getMessage() . "\n";
    }
    
    // Clean up token
    $testUser->tokens()->where('name', 'debug-token')->delete();
    
} else {
    echo "   No OAuth users available for testing\n";
}

// 7. Check common issues
echo "\n7. Common Issues Check:\n";

// Check if servers are running
echo "   Backend server (port 8000): ";
try {
    $response = Http::timeout(5)->get('http://127.0.0.1:8000/api/auth/providers');
    echo $response->successful() ? "✅ Running\n" : "❌ Not responding\n";
} catch (\Exception $e) {
    echo "❌ Not running or not accessible\n";
}

echo "   Frontend server (port 5175): ";
try {
    $response = Http::timeout(5)->get('http://localhost:5175');
    echo $response->successful() ? "✅ Running\n" : "❌ Not responding\n";
} catch (\Exception $e) {
    echo "❌ Not running or not accessible\n";
}

// Check database
echo "   Database connection: ";
try {
    $userCount = User::count();
    echo "✅ Connected ({$userCount} users)\n";
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

// Check cryptocurrencies for wallet creation
$cryptoCount = \App\Models\Cryptocurrency::where('is_active', true)->count();
echo "   Active cryptocurrencies: {$cryptoCount}\n";
if ($cryptoCount === 0) {
    echo "   ⚠️  WARNING: No cryptocurrencies found. Run: php artisan db:seed --class=CryptocurrencySeeder\n";
}

echo "\n=== Troubleshooting Steps ===\n";
echo "1. If OAuth user is created but dashboard not loading:\n";
echo "   - Check browser console for JavaScript errors\n";
echo "   - Verify AuthContext is receiving user data\n";
echo "   - Check if ProtectedRoute is working correctly\n\n";

echo "2. If OAuth callback fails:\n";
echo "   - Update Google OAuth redirect URI in Google Cloud Console\n";
echo "   - Add: http://localhost:8000/api/auth/google/callback\n\n";

echo "3. If user data is not persisting:\n";
echo "   - Check localStorage for auth_token\n";
echo "   - Verify token is being sent with API requests\n";
echo "   - Check if token is valid and not expired\n\n";

echo "4. If dashboard shows loading indefinitely:\n";
echo "   - Check API endpoints are responding\n";
echo "   - Verify user has required wallets\n";
echo "   - Check for CORS issues\n\n";

echo "✅ Debug complete. Check the issues above and follow troubleshooting steps.\n";