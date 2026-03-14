<?php

/**
 * Real-time OAuth Debug - Monitor actual OAuth attempts
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\OAuthSession;

echo "=== Real-time OAuth Debug ===\n\n";

// 1. Check very recent OAuth activity (last 2 minutes)
echo "1. Very Recent OAuth Activity (last 2 minutes):\n";
$veryRecentSessions = OAuthSession::where('created_at', '>', now()->subMinutes(2))
    ->orderBy('created_at', 'desc')
    ->get();

if ($veryRecentSessions->count() > 0) {
    foreach ($veryRecentSessions as $session) {
        echo "   Session: " . substr($session->state, 0, 15) . "...\n";
        echo "   Provider: {$session->provider}\n";
        echo "   Created: {$session->created_at}\n";
        echo "   Redirect URL: {$session->redirect_url}\n";
        echo "   Status: " . ($session->expires_at > now() ? 'Active' : 'Expired') . "\n\n";
    }
} else {
    echo "   No very recent OAuth sessions found.\n";
}

// 2. Check your user's recent token activity
echo "2. Your Recent Token Activity:\n";
$yourUser = User::where('email', 'ashenafi14264@gmail.com')->first();

if ($yourUser) {
    $recentTokens = $yourUser->tokens()
        ->where('created_at', '>', now()->subMinutes(5))
        ->orderBy('created_at', 'desc')
        ->get();
    
    if ($recentTokens->count() > 0) {
        echo "   Recent tokens (last 5 minutes):\n";
        foreach ($recentTokens as $token) {
            echo "     - {$token->name}: created {$token->created_at}\n";
        }
    } else {
        echo "   No recent tokens created.\n";
    }
    
    echo "\n   Total active tokens: " . $yourUser->tokens()->count() . "\n";
    echo "   Last updated: {$yourUser->updated_at}\n";
} else {
    echo "   User not found.\n";
}

// 3. Test the OAuth callback endpoint directly
echo "\n3. Testing OAuth Callback Endpoint:\n";

// Create a test session for callback testing
$testSession = OAuthSession::create([
    'state' => 'realtime-test-' . time(),
    'provider' => 'google',
    'redirect_url' => 'http://localhost:5175/dashboard',
    'expires_at' => now()->addMinutes(10),
]);

echo "   Created test session: {$testSession->state}\n";

// Test callback with mock data
try {
    $mockCode = 'test-code-' . time();
    $mockState = $testSession->state;
    
    // Test the callback URL structure
    $callbackUrl = "http://127.0.0.1:8000/api/auth/google/callback?code={$mockCode}&state={$mockState}";
    echo "   Test callback URL: {$callbackUrl}\n";
    
    // We can't easily test the full callback without Google, but we can verify the endpoint exists
    $response = \Illuminate\Support\Facades\Http::timeout(5)
        ->get('http://127.0.0.1:8000/api/auth/google/callback?error=test');
    
    if ($response->status() === 302) {
        echo "   ✅ Callback endpoint is responding (redirect as expected)\n";
        $location = $response->header('Location');
        if ($location) {
            echo "   Redirect location: {$location}\n";
        }
    } else {
        echo "   ⚠️  Callback endpoint status: " . $response->status() . "\n";
    }
    
} catch (\Exception $e) {
    echo "   ❌ Callback test error: " . $e->getMessage() . "\n";
}

// Clean up test session
$testSession->delete();

// 4. Check for any OAuth errors in recent activity
echo "\n4. Checking for OAuth Issues:\n";

// Check if there are any failed OAuth attempts
$allRecentSessions = OAuthSession::where('created_at', '>', now()->subHour())->get();
$expiredSessions = $allRecentSessions->where('expires_at', '<', now());

if ($expiredSessions->count() > 0) {
    echo "   ⚠️  Found {$expiredSessions->count()} expired sessions (possible failed attempts)\n";
    foreach ($expiredSessions as $expired) {
        echo "     - State: " . substr($expired->state, 0, 15) . "... (expired: {$expired->expires_at})\n";
    }
} else {
    echo "   ✅ No expired sessions found\n";
}

// 5. Generate a fresh test URL
echo "\n5. Fresh Test URL Generation:\n";

if ($yourUser) {
    // Generate a new token
    $freshToken = $yourUser->createToken('realtime-debug-' . time())->plainTextToken;
    
    $userData = [
        'id' => $yourUser->id,
        'name' => $yourUser->name,
        'email' => $yourUser->email,
        'avatar' => $yourUser->avatar,
        'is_admin' => $yourUser->is_admin,
    ];
    
    $freshCallbackUrl = config('services.frontend.url') . '/login?' . http_build_query([
        'auth_success' => 'true',
        'token' => $freshToken,
        'user' => base64_encode(json_encode($userData)),
    ]);
    
    echo "   Fresh callback URL:\n";
    echo "   {$freshCallbackUrl}\n\n";
    
    echo "   This URL should:\n";
    echo "   - Redirect you to dashboard immediately\n";
    echo "   - Set authentication state properly\n";
    echo "   - Not require any page refresh\n\n";
}

echo "=== DEBUGGING STEPS ===\n";
echo "1. Try the fresh callback URL above\n";
echo "2. If it works, the issue is in the OAuth flow itself\n";
echo "3. If it doesn't work, the issue is in the frontend handling\n\n";

echo "To debug the real OAuth flow:\n";
echo "1. Open browser dev tools (F12)\n";
echo "2. Go to Network tab\n";
echo "3. Try OAuth login again\n";
echo "4. Look for:\n";
echo "   - Failed network requests\n";
echo "   - JavaScript console errors\n";
echo "   - Redirect loops\n";
echo "   - CORS errors\n\n";

echo "✅ Real-time debug complete. Run this again after attempting OAuth.\n";