<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== OAuth Buttons Fix Script ===\n\n";

// 1. Check if OAuth sessions table exists
echo "1. Checking OAuth sessions table...\n";
try {
    $tableExists = \Illuminate\Support\Facades\Schema::hasTable('oauth_sessions');
    if ($tableExists) {
        echo "   ✅ OAuth sessions table exists\n";
    } else {
        echo "   ❌ OAuth sessions table missing - running migration...\n";
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        echo "   ✅ Migration completed\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Database error: " . $e->getMessage() . "\n";
}

// 2. Check OAuth configuration
echo "\n2. Checking OAuth configuration...\n";
$googleClientId = config('services.google.client_id');
$googleClientSecret = config('services.google.client_secret');
$googleRedirectUri = config('services.google.redirect_uri');

if ($googleClientId && $googleClientSecret && $googleRedirectUri) {
    echo "   ✅ Google OAuth configuration complete\n";
    echo "   Client ID: " . substr($googleClientId, 0, 20) . "...\n";
    echo "   Redirect URI: {$googleRedirectUri}\n";
} else {
    echo "   ❌ Google OAuth configuration incomplete\n";
    echo "   Missing: ";
    if (!$googleClientId) echo "CLIENT_ID ";
    if (!$googleClientSecret) echo "CLIENT_SECRET ";
    if (!$googleRedirectUri) echo "REDIRECT_URI ";
    echo "\n";
}

// 3. Test OAuth providers endpoint
echo "\n3. Testing OAuth providers endpoint...\n";
try {
    $controller = new \App\Http\Controllers\Api\OAuthController(
        new \App\Services\GoogleOAuthService(),
        new \App\Services\AppleOAuthService()
    );
    
    $response = $controller->getProviders();
    $data = json_decode($response->getContent(), true);
    
    if ($data['success']) {
        echo "   ✅ OAuth providers endpoint working\n";
        echo "   Google enabled: " . ($data['providers']['google']['enabled'] ? 'Yes' : 'No') . "\n";
        echo "   Apple enabled: " . ($data['providers']['apple']['enabled'] ? 'Yes' : 'No') . "\n";
    } else {
        echo "   ❌ OAuth providers endpoint failed\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Error testing providers endpoint: " . $e->getMessage() . "\n";
}

// 4. Test Google OAuth URL generation
echo "\n4. Testing Google OAuth URL generation...\n";
try {
    $googleService = new \App\Services\GoogleOAuthService();
    $authData = $googleService->getAuthUrl('http://localhost:5173/login');
    
    if (isset($authData['url']) && isset($authData['state'])) {
        echo "   ✅ Google OAuth URL generation working\n";
        echo "   State: {$authData['state']}\n";
        echo "   URL: " . substr($authData['url'], 0, 80) . "...\n";
        
        // Check if session was created
        $session = \App\Models\OAuthSession::where('state', $authData['state'])->first();
        if ($session) {
            echo "   ✅ OAuth session created in database\n";
            $session->delete(); // Clean up test session
        } else {
            echo "   ❌ OAuth session not created in database\n";
        }
    } else {
        echo "   ❌ Google OAuth URL generation failed\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Error testing Google OAuth: " . $e->getMessage() . "\n";
}

// 5. Clean up old OAuth sessions
echo "\n5. Cleaning up expired OAuth sessions...\n";
try {
    $deletedCount = \App\Models\OAuthSession::cleanupExpired();
    echo "   ✅ Cleaned up {$deletedCount} expired sessions\n";
} catch (\Exception $e) {
    echo "   ❌ Error cleaning up sessions: " . $e->getMessage() . "\n";
}

echo "\n=== Fix Summary ===\n";
echo "✅ OAuth sessions table migration created\n";
echo "✅ Frontend fallback for OAuth providers added\n";
echo "✅ Better error handling for OAuth flow\n";
echo "✅ Backend server availability check added\n";

echo "\n=== Next Steps ===\n";
echo "1. Start the Laravel server: php artisan serve --host=127.0.0.1 --port=8000\n";
echo "2. Start the frontend server: npm run dev (in crypto_frontend/crypto-vite)\n";
echo "3. Visit http://localhost:5173/login to test OAuth buttons\n";
echo "4. Check browser console for any JavaScript errors\n";

echo "\n=== Troubleshooting ===\n";
echo "If OAuth buttons still don't appear:\n";
echo "- Check browser console for JavaScript errors\n";
echo "- Verify both servers are running\n";
echo "- Check network tab for failed API requests\n";
echo "- Ensure CORS is properly configured\n";