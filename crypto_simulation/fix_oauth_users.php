<?php

/**
 * Fix OAuth Users - Set email verification and ensure proper setup
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "=== Fixing OAuth Users ===\n\n";

// 1. Fix all OAuth users
$oauthUsers = User::whereIn('provider', ['google', 'apple'])->get();

if ($oauthUsers->count() > 0) {
    echo "Found {$oauthUsers->count()} OAuth users to fix:\n\n";
    
    foreach ($oauthUsers as $user) {
        echo "Fixing user: {$user->name} ({$user->email})\n";
        
        $updates = [];
        
        // Set email as verified for OAuth users (Google/Apple emails are pre-verified)
        if (!$user->email_verified_at) {
            $updates['email_verified_at'] = now();
            echo "   ✅ Setting email as verified\n";
        }
        
        // Ensure user has all required wallets
        $walletCount = $user->wallets()->count();
        $cryptoCount = \App\Models\Cryptocurrency::where('is_active', true)->count();
        
        if ($walletCount < $cryptoCount) {
            echo "   ⚠️  User has {$walletCount} wallets, should have {$cryptoCount}\n";
            echo "   Creating missing wallets...\n";
            
            $existingSymbols = $user->wallets()->pluck('cryptocurrency_symbol')->toArray();
            $allCryptos = \App\Models\Cryptocurrency::where('is_active', true)->get();
            
            foreach ($allCryptos as $crypto) {
                if (!in_array($crypto->symbol, $existingSymbols)) {
                    $balance = $crypto->symbol === 'USD' ? '10000.00000000' : '0.00000000';
                    
                    \App\Models\Wallet::create([
                        'user_id' => $user->id,
                        'cryptocurrency_symbol' => $crypto->symbol,
                        'balance' => $balance,
                        'reserved_balance' => '0.00000000',
                    ]);
                    
                    echo "   ✅ Created wallet for {$crypto->symbol}\n";
                }
            }
        } else {
            echo "   ✅ User has all required wallets ({$walletCount})\n";
        }
        
        // Apply updates
        if (!empty($updates)) {
            $user->update($updates);
            echo "   ✅ User updated\n";
        }
        
        echo "   Final status:\n";
        echo "     - Email verified: " . ($user->fresh()->email_verified_at ? 'Yes' : 'No') . "\n";
        echo "     - Admin: " . ($user->is_admin ? 'Yes' : 'No') . "\n";
        echo "     - Wallets: " . $user->wallets()->count() . "\n";
        echo "     - Tokens: " . $user->tokens()->count() . "\n\n";
    }
} else {
    echo "No OAuth users found.\n\n";
}

// 2. Clean up old OAuth sessions
echo "2. Cleaning up expired OAuth sessions...\n";
$expiredSessions = \App\Models\OAuthSession::where('expires_at', '<', now())->get();
if ($expiredSessions->count() > 0) {
    foreach ($expiredSessions as $session) {
        echo "   Deleting expired session: " . substr($session->state, 0, 20) . "...\n";
        $session->delete();
    }
    echo "   ✅ Cleaned up {$expiredSessions->count()} expired sessions\n";
} else {
    echo "   ✅ No expired sessions to clean up\n";
}

// 3. Test OAuth user authentication
echo "\n3. Testing OAuth User Authentication:\n";
$testUser = User::where('provider', 'google')->where('email', 'ashenafi14264@gmail.com')->first();

if ($testUser) {
    echo "   Testing user: {$testUser->name} ({$testUser->email})\n";
    
    // Create a test token
    $token = $testUser->createToken('dashboard-test')->plainTextToken;
    echo "   ✅ Token created: " . substr($token, 0, 30) . "...\n";
    
    // Test API call
    try {
        $response = \Illuminate\Support\Facades\Http::withToken($token)
            ->timeout(10)
            ->get('http://127.0.0.1:8000/api/auth/user');
        
        if ($response->successful()) {
            $userData = $response->json();
            echo "   ✅ API authentication working\n";
            echo "   User data received:\n";
            echo "     - ID: " . $userData['user']['id'] . "\n";
            echo "     - Name: " . $userData['user']['name'] . "\n";
            echo "     - Email: " . $userData['user']['email'] . "\n";
            echo "     - Admin: " . ($userData['user']['is_admin'] ? 'Yes' : 'No') . "\n";
            echo "     - Email Verified: " . ($userData['user']['email_verified_at'] ? 'Yes' : 'No') . "\n";
        } else {
            echo "   ❌ API authentication failed: " . $response->status() . "\n";
        }
    } catch (\Exception $e) {
        echo "   ❌ API test error: " . $e->getMessage() . "\n";
    }
    
    // Clean up test token
    $testUser->tokens()->where('name', 'dashboard-test')->delete();
    echo "   ✅ Test token cleaned up\n";
    
} else {
    echo "   No test user found (ashenafi14264@gmail.com)\n";
}

// 4. Generate OAuth callback URL for testing
echo "\n4. OAuth Callback URL for Testing:\n";
$testUserData = [
    'id' => 20,
    'name' => 'Ashenafi Ashu',
    'email' => 'ashenafi14264@gmail.com',
    'avatar' => null,
    'is_admin' => false,
];

$callbackUrl = config('services.frontend.url') . '/login?' . http_build_query([
    'auth_success' => 'true',
    'token' => 'test-jwt-token-here',
    'user' => base64_encode(json_encode($testUserData)),
]);

echo "   Test callback URL:\n";
echo "   {$callbackUrl}\n\n";

echo "   You can test this URL in your browser to simulate OAuth callback\n";

echo "\n=== Fix Complete ===\n";
echo "✅ OAuth users have been fixed\n";
echo "✅ Email verification set for all OAuth users\n";
echo "✅ All users have required wallets\n";
echo "✅ Expired sessions cleaned up\n\n";

echo "Next steps:\n";
echo "1. Try OAuth login again at: http://localhost:5175/login\n";
echo "2. After Google OAuth, you should be redirected to dashboard\n";
echo "3. If still having issues, check browser console for errors\n\n";

echo "✅ OAuth system should now work correctly!\n";