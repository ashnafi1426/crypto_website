<?php

/**
 * Check Recent OAuth Activity
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\OAuthSession;

echo "=== Recent OAuth Activity Check ===\n\n";

// 1. Check OAuth sessions from last 24 hours
echo "1. OAuth Sessions (last 24 hours):\n";
$sessions = OAuthSession::where('created_at', '>', now()->subDay())
    ->orderBy('created_at', 'desc')
    ->get();

if ($sessions->count() > 0) {
    foreach ($sessions as $session) {
        echo "   - State: " . substr($session->state, 0, 15) . "...\n";
        echo "     Provider: {$session->provider}\n";
        echo "     Redirect: {$session->redirect_url}\n";
        echo "     Created: {$session->created_at}\n";
        echo "     Status: " . ($session->expires_at > now() ? 'Active' : 'Expired') . "\n\n";
    }
} else {
    echo "   No OAuth sessions found in last 24 hours\n";
}

// 2. Check OAuth users from last 24 hours
echo "2. OAuth Users (last 24 hours):\n";
$users = User::whereIn('provider', ['google', 'apple'])
    ->where('created_at', '>', now()->subDay())
    ->orderBy('created_at', 'desc')
    ->get();

if ($users->count() > 0) {
    foreach ($users as $user) {
        echo "   - {$user->name} ({$user->email})\n";
        echo "     Provider: {$user->provider}\n";
        echo "     Admin: " . ($user->is_admin ? 'Yes' : 'No') . "\n";
        echo "     Tokens: " . $user->tokens()->count() . "\n";
        echo "     Wallets: " . $user->wallets()->count() . "\n";
        echo "     Created: {$user->created_at}\n\n";
    }
} else {
    echo "   No OAuth users created in last 24 hours\n";
}

// 3. Check Laravel logs for OAuth errors
echo "3. Recent OAuth Errors:\n";
$logPath = storage_path('logs/laravel.log');
if (file_exists($logPath)) {
    $logContent = file_get_contents($logPath);
    $lines = explode("\n", $logContent);
    $recentLines = array_slice($lines, -100); // Last 100 lines
    
    $oauthErrors = [];
    foreach ($recentLines as $line) {
        if (strpos($line, 'OAuth') !== false && (strpos($line, 'ERROR') !== false || strpos($line, 'error') !== false)) {
            $oauthErrors[] = $line;
        }
    }
    
    if (!empty($oauthErrors)) {
        foreach (array_slice($oauthErrors, -5) as $error) { // Last 5 errors
            echo "   " . $error . "\n";
        }
    } else {
        echo "   No recent OAuth errors found\n";
    }
} else {
    echo "   Log file not found\n";
}

// 4. Test current OAuth configuration
echo "\n4. Current OAuth Configuration:\n";
echo "   Google Client ID: " . (config('services.google.client_id') ? 'Set' : 'Not set') . "\n";
echo "   Google Redirect URI: " . config('services.google.redirect_uri') . "\n";
echo "   Frontend URL: " . config('services.frontend.url') . "\n";
echo "   Environment: " . app()->environment() . "\n";

// 5. Check if servers are running
echo "\n5. Server Status Check:\n";
try {
    // Test backend
    $backendResponse = file_get_contents('http://127.0.0.1:8000/api/auth/providers', false, stream_context_create([
        'http' => ['timeout' => 5]
    ]));
    echo "   Backend (8000): " . ($backendResponse ? '✅ Running' : '❌ Not responding') . "\n";
} catch (\Exception $e) {
    echo "   Backend (8000): ❌ Not running or not accessible\n";
}

try {
    // Test frontend (this will likely fail from PHP, but we can try)
    $frontendResponse = @file_get_contents('http://localhost:5175', false, stream_context_create([
        'http' => ['timeout' => 2]
    ]));
    echo "   Frontend (5175): " . ($frontendResponse ? '✅ Running' : '❌ Not responding') . "\n";
} catch (\Exception $e) {
    echo "   Frontend (5175): ❌ Not accessible from backend (normal)\n";
}

// 6. Generate a fresh test OAuth URL
echo "\n6. Fresh OAuth Test:\n";
try {
    $googleService = app(\App\Services\GoogleOAuthService::class);
    $authData = $googleService->getAuthUrl('http://localhost:5175/dashboard');
    
    echo "   ✅ OAuth URL generated successfully\n";
    echo "   Test URL: " . substr($authData['url'], 0, 80) . "...\n";
    echo "   State: " . $authData['state'] . "\n";
    
    // Clean up
    $session = OAuthSession::where('state', $authData['state'])->first();
    if ($session) {
        $session->delete();
        echo "   ✅ Test session cleaned up\n";
    }
    
} catch (\Exception $e) {
    echo "   ❌ OAuth URL generation failed: " . $e->getMessage() . "\n";
}

echo "\n=== Recommendations ===\n";
echo "1. Make sure both servers are running:\n";
echo "   - Backend: php artisan serve --host=127.0.0.1 --port=8000\n";
echo "   - Frontend: npm run dev --port 5175\n\n";

echo "2. Test OAuth callback manually:\n";
echo "   - Open: http://localhost:5175/test_oauth_callback.html\n";
echo "   - Click 'Test OAuth Callback'\n";
echo "   - Check browser console for errors\n\n";

echo "3. If OAuth still fails:\n";
echo "   - Check Google Cloud Console redirect URI settings\n";
echo "   - Verify browser isn't blocking redirects\n";
echo "   - Check for JavaScript errors in browser console\n\n";

echo "✅ OAuth activity check complete!\n";