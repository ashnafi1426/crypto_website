<?php

/**
 * Check OAuth Configuration
 * 
 * This script shows exactly what redirect URI is being used
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== OAuth Configuration Check ===\n\n";

// Check environment variables
echo "1. Environment Variables:\n";
echo "   GOOGLE_CLIENT_ID: " . config('services.google.client_id') . "\n";
echo "   GOOGLE_CLIENT_SECRET: " . (config('services.google.client_secret') ? '[SET]' : '[NOT SET]') . "\n";
echo "   GOOGLE_REDIRECT_URI: " . config('services.google.redirect_uri') . "\n\n";

// Generate OAuth URL to see what redirect URI is actually being used
echo "2. Generated OAuth URL:\n";
try {
    $googleService = app(\App\Services\GoogleOAuthService::class);
    $authData = $googleService->getAuthUrl();
    
    // Parse the URL to extract redirect_uri parameter
    $parsedUrl = parse_url($authData['url']);
    parse_str($parsedUrl['query'], $queryParams);
    
    echo "   Full OAuth URL: " . $authData['url'] . "\n\n";
    echo "   Extracted redirect_uri: " . urldecode($queryParams['redirect_uri']) . "\n\n";
    
    // Clean up test session
    $session = \App\Models\OAuthSession::where('state', $authData['state'])->first();
    if ($session) {
        $session->delete();
    }
    
} catch (\Exception $e) {
    echo "   Error generating OAuth URL: " . $e->getMessage() . "\n\n";
}

echo "3. What to add to Google Console:\n";
echo "   Go to: https://console.cloud.google.com/\n";
echo "   Navigate to: APIs & Services → Credentials\n";
echo "   Find your OAuth client ID\n";
echo "   Add this EXACT redirect URI:\n\n";
echo "   ┌─────────────────────────────────────────────────────────────┐\n";
echo "   │ http://localhost:8000/api/auth/google/callback              │\n";
echo "   └─────────────────────────────────────────────────────────────┘\n\n";

echo "4. Common Mistakes to Avoid:\n";
echo "   ❌ https://localhost:8000/api/auth/google/callback (wrong protocol)\n";
echo "   ❌ http://localhost:8000/api/auth/google/callback/ (trailing slash)\n";
echo "   ❌ http://localhost:3000/api/auth/google/callback (wrong port)\n";
echo "   ✅ http://localhost:8000/api/auth/google/callback (correct!)\n\n";

echo "5. Your Client ID for reference:\n";
echo "   " . config('services.google.client_id') . "\n\n";

echo "=== Instructions ===\n";
echo "1. Copy the redirect URI above\n";
echo "2. Go to Google Cloud Console\n";
echo "3. Add it to your OAuth client\n";
echo "4. Save changes\n";
echo "5. Test OAuth login again\n";
