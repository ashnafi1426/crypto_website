<?php

/**
 * Live OAuth Flow Debug - Real-time monitoring
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\OAuthSession;

echo "=== Live OAuth Flow Debug ===\n\n";

// 1. Check recent OAuth activity
echo "1. Recent OAuth Activity (last 10 minutes):\n";
$recentSessions = OAuthSession::where('created_at', '>', now()->subMinutes(10))
    ->orderBy('created_at', 'desc')
    ->get();

if ($recentSessions->count() > 0) {
    foreach ($recentSessions as $session) {
        echo "   Session: " . substr($session->state, 0, 15) . "...\n";
        echo "   Provider: {$session->provider}\n";
        echo "   Created: {$session->created_at}\n";
        echo "   Redirect URL: {$session->redirect_url}\n";
        echo "   Status: " . ($session->expires_at > now() ? 'Active' : 'Expired') . "\n\n";
    }
} else {
    echo "   No recent OAuth sessions found.\n";
}

// 2. Check recent user activity
echo "2. Recent User Activity:\n";
$recentUsers = User::whereIn('provider', ['google', 'apple'])
    ->where('updated_at', '>', now()->subMinutes(10))
    ->orderBy('updated_at', 'desc')
    ->get();

if ($recentUsers->count() > 0) {
    foreach ($recentUsers as $user) {
        echo "   User: {$user->name} ({$user->email})\n";
        echo "   Provider: {$user->provider}\n";
        echo "   Last updated: {$user->updated_at}\n";
        echo "   Email verified: " . ($user->email_verified_at ? 'Yes' : 'No') . "\n";
        echo "   Active tokens: " . $user->tokens()->count() . "\n\n";
    }
} else {
    echo "   No recent user activity.\n";
}

// 3. Check your specific user
echo "3. Your User Status:\n";
$yourUser = User::where('email', 'ashenafi14264@gmail.com')->first();

if ($yourUser) {
    echo "   ✅ User found: {$yourUser->name}\n";
    echo "   ID: {$yourUser->id}\n";
    echo "   Provider: {$yourUser->provider}\n";
    echo "   Admin: " . ($yourUser->is_admin ? 'Yes' : 'No') . "\n";
    echo "   Email verified: " . ($yourUser->email_verified_at ? 'Yes' : 'No') . "\n";
    echo "   Created: {$yourUser->created_at}\n";
    echo "   Last updated: {$yourUser->updated_at}\n";
    echo "   Active tokens: " . $yourUser->tokens()->count() . "\n";
    echo "   Wallets: " . $yourUser->wallets()->count() . "\n\n";
    
    // Show recent tokens
    $recentTokens = $yourUser->tokens()->where('created_at', '>', now()->subHour())->get();
    if ($recentTokens->count() > 0) {
        echo "   Recent tokens (last hour):\n";
        foreach ($recentTokens as $token) {
            echo "     - {$token->name}: " . substr($token->token, 0, 20) . "... (created: {$token->created_at})\n";
        }
        echo "\n";
    }
} else {
    echo "   ❌ User not found: ashenafi14264@gmail.com\n\n";
}

// 4. Test OAuth callback URL generation
echo "4. Testing OAuth Callback URL Generation:\n";
try {
    $googleService = app(\App\Services\GoogleOAuthService::class);
    $authData = $googleService->getAuthUrl('http://localhost:5175/dashboard');
    
    echo "   ✅ OAuth URL generated successfully\n";
    echo "   State: {$authData['state']}\n";
    echo "   URL: " . substr($authData['url'], 0, 100) . "...\n";
    
    // Check if session was created
    $session = OAuthSession::where('state', $authData['state'])->first();
    if ($session) {
        echo "   ✅ Session created in database\n";
        echo "   Redirect URL: {$session->redirect_url}\n";
        
        // Clean up test session
        $session->delete();
        echo "   ✅ Test session cleaned up\n";
    }
    
} catch (\Exception $e) {
    echo "   ❌ OAuth URL generation failed: " . $e->getMessage() . "\n";
}

// 5. Check Google OAuth configuration
echo "\n5. Google OAuth Configuration:\n";
echo "   Client ID: " . config('services.google.client_id') . "\n";
echo "   Redirect URI: " . config('services.google.redirect_uri') . "\n";
echo "   Frontend URL: " . config('services.frontend.url') . "\n";

// 6. Test the actual OAuth callback endpoint
echo "\n6. Testing OAuth Callback Endpoint:\n";
try {
    $response = \Illuminate\Support\Facades\Http::timeout(10)
        ->get('http://127.0.0.1:8000/api/auth/google/callback?error=access_denied');
    
    if ($response->status() === 302) {
        echo "   ✅ Callback endpoint responding (redirect as expected)\n";
        $location = $response->header('Location');
        if ($location) {
            echo "   Redirect location: {$location}\n";
        }
    } else {
        echo "   ⚠️  Callback endpoint status: " . $response->status() . "\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Callback endpoint error: " . $e->getMessage() . "\n";
}

echo "\n=== DEBUGGING INSTRUCTIONS ===\n";
echo "To debug your OAuth flow:\n\n";

echo "1. Open browser developer tools (F12)\n";
echo "2. Go to Network tab\n";
echo "3. Visit: http://localhost:5175/login\n";
echo "4. Click 'Continue with Google'\n";
echo "5. Complete Google authentication\n";
echo "6. Watch the network requests and console logs\n\n";

echo "Look for these specific things:\n";
echo "- OAuth redirect to Google (should work)\n";
echo "- Callback to /api/auth/google/callback (check for errors)\n";
echo "- Final redirect to frontend with auth_success=true\n";
echo "- JavaScript console errors in frontend\n\n";

echo "Common issues to check:\n";
echo "- Google OAuth redirect URI mismatch\n";
echo "- CORS issues between frontend and backend\n";
echo "- JavaScript errors in AuthContext\n";
echo "- Token not being stored in localStorage\n\n";

echo "✅ Run this script again after attempting OAuth to see new activity.\n";