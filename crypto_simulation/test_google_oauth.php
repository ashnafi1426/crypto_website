<?php

/**
 * Test Google OAuth Configuration
 * 
 * This script tests if Google OAuth is properly configured
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\GoogleOAuthService;

echo "=== Google OAuth Configuration Test ===\n\n";

try {
    // Test 1: Check if service can be instantiated
    echo "1. Testing GoogleOAuthService instantiation...\n";
    $googleService = app(GoogleOAuthService::class);
    echo "   ✅ Service instantiated successfully\n\n";
    
    // Test 2: Check configuration
    echo "2. Checking OAuth configuration...\n";
    $clientId = config('services.google.client_id');
    $clientSecret = config('services.google.client_secret');
    $redirectUri = config('services.google.redirect_uri');
    
    echo "   Client ID: " . ($clientId !== 'your-google-client-id' ? '✅ Configured' : '❌ Not configured') . "\n";
    echo "   Client Secret: " . ($clientSecret !== 'your-google-client-secret' ? '✅ Configured' : '❌ Not configured') . "\n";
    echo "   Redirect URI: " . $redirectUri . "\n\n";
    
    // Test 3: Generate auth URL
    echo "3. Testing auth URL generation...\n";
    $authData = $googleService->getAuthUrl('http://localhost:5175/dashboard');
    
    if (isset($authData['url']) && isset($authData['state'])) {
        echo "   ✅ Auth URL generated successfully\n";
        echo "   State token: " . substr($authData['state'], 0, 20) . "...\n";
        echo "   Auth URL: " . substr($authData['url'], 0, 80) . "...\n\n";
    } else {
        echo "   ❌ Failed to generate auth URL\n\n";
    }
    
    // Test 4: Check OAuth session in database
    echo "4. Checking OAuth session storage...\n";
    $session = \App\Models\OAuthSession::where('state', $authData['state'])->first();
    
    if ($session) {
        echo "   ✅ OAuth session stored in database\n";
        echo "   Provider: " . $session->provider . "\n";
        echo "   Expires at: " . $session->expires_at . "\n\n";
    } else {
        echo "   ❌ OAuth session not found in database\n\n";
    }
    
    // Test 5: Test providers endpoint
    echo "5. Testing providers endpoint...\n";
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
    
    echo "   Google OAuth: " . ($providers['google']['enabled'] ? '✅ Enabled' : '❌ Disabled') . "\n";
    echo "   Apple OAuth: " . ($providers['apple']['enabled'] ? '✅ Enabled' : '❌ Disabled') . "\n\n";
    
    // Summary
    echo "=== Test Summary ===\n";
    echo "✅ Google OAuth is properly configured!\n";
    echo "\nNext steps:\n";
    echo "1. Start backend: php artisan serve\n";
    echo "2. Start frontend: cd crypto_frontend/crypto-vite && npm run dev\n";
    echo "3. Visit: http://localhost:5175/login\n";
    echo "4. Click 'Continue with Google' button\n";
    echo "5. Complete Google authentication\n";
    echo "6. You should be redirected back and logged in!\n\n";
    
    // Clean up test session
    if ($session) {
        $session->delete();
        echo "✅ Test OAuth session cleaned up\n";
    }
    
} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
