<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\AppleOAuthService;
use Illuminate\Support\Facades\Config;

/**
 * Apple OAuth Test Script
 * Tests Apple Sign-In configuration and functionality
 */

echo "🍎 Apple OAuth Test Script\n";
echo "==========================\n\n";

// Load Laravel configuration
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    // Test 1: Configuration Check
    echo "1. Testing Apple OAuth Configuration...\n";
    
    $config = [
        'client_id' => config('services.apple.client_id'),
        'team_id' => config('services.apple.team_id'),
        'key_id' => config('services.apple.key_id'),
        'private_key_path' => config('services.apple.private_key_path'),
        'redirect_uri' => config('services.apple.redirect_uri'),
    ];
    
    foreach ($config as $key => $value) {
        if (empty($value)) {
            echo "   ❌ $key: Not configured\n";
        } else {
            echo "   ✅ $key: $value\n";
        }
    }
    
    // Test 2: Private Key File
    echo "\n2. Testing Private Key File...\n";
    $privateKeyPath = storage_path($config['private_key_path']);
    
    if (file_exists($privateKeyPath)) {
        echo "   ✅ Private key file exists: $privateKeyPath\n";
        
        $keyContent = file_get_contents($privateKeyPath);
        if (strpos($keyContent, '-----BEGIN PRIVATE KEY-----') !== false) {
            echo "   ✅ Private key format appears valid\n";
        } else {
            echo "   ⚠️  Private key format may be invalid (sample key detected)\n";
        }
    } else {
        echo "   ❌ Private key file not found: $privateKeyPath\n";
    }
    
    // Test 3: Apple OAuth Service
    echo "\n3. Testing Apple OAuth Service...\n";
    
    try {
        $appleService = new AppleOAuthService();
        echo "   ✅ AppleOAuthService instantiated successfully\n";
        
        // Test auth URL generation
        $authData = $appleService->getAuthUrl('http://localhost:5175/login');
        echo "   ✅ Auth URL generated successfully\n";
        echo "   📝 Auth URL: " . substr($authData['url'], 0, 100) . "...\n";
        echo "   🔑 State: " . $authData['state'] . "\n";
        
    } catch (Exception $e) {
        echo "   ❌ AppleOAuthService error: " . $e->getMessage() . "\n";
    }
    
    // Test 4: Database Schema
    echo "\n4. Testing Database Schema...\n";
    
    try {
        $pdo = new PDO('sqlite:' . database_path('database.sqlite'));
        
        // Check users table structure
        $stmt = $pdo->query("PRAGMA table_info(users)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $requiredFields = ['provider', 'provider_id'];
        $existingFields = array_column($columns, 'name');
        
        foreach ($requiredFields as $field) {
            if (in_array($field, $existingFields)) {
                echo "   ✅ Database field '$field' exists\n";
            } else {
                echo "   ❌ Database field '$field' missing - run: php artisan migrate\n";
            }
        }
        
        // Check oauth_sessions table
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='oauth_sessions'");
        if ($stmt->fetch()) {
            echo "   ✅ oauth_sessions table exists\n";
        } else {
            echo "   ❌ oauth_sessions table missing - run: php artisan migrate\n";
        }
        
    } catch (Exception $e) {
        echo "   ❌ Database check failed: " . $e->getMessage() . "\n";
    }
    
    // Test 5: API Endpoints
    echo "\n5. Testing API Endpoints...\n";
    
    $baseUrl = config('app.url');
    $endpoints = [
        '/api/auth/providers' => 'OAuth providers list',
        '/api/auth/apple' => 'Apple OAuth redirect',
        '/api/auth/apple/callback' => 'Apple OAuth callback'
    ];
    
    foreach ($endpoints as $endpoint => $description) {
        $url = $baseUrl . $endpoint;
        echo "   📍 $description: $url\n";
    }
    
    // Test 6: Frontend Configuration
    echo "\n6. Testing Frontend Configuration...\n";
    
    $frontendUrl = config('services.frontend.url');
    echo "   🌐 Frontend URL: $frontendUrl\n";
    
    $corsOrigins = config('cors.allowed_origins', []);
    if (in_array($frontendUrl, $corsOrigins) || in_array('*', $corsOrigins)) {
        echo "   ✅ CORS configured for frontend\n";
    } else {
        echo "   ⚠️  CORS may need configuration for frontend\n";
    }
    
    // Test Results Summary
    echo "\n📊 Test Results Summary:\n";
    echo "========================\n";
    
    $allConfigured = !empty($config['client_id']) && 
                    !empty($config['team_id']) && 
                    !empty($config['key_id']) && 
                    file_exists($privateKeyPath);
    
    if ($allConfigured) {
        echo "✅ Apple OAuth appears to be configured correctly!\n\n";
        
        echo "🚀 Next Steps:\n";
        echo "1. Ensure you have a real Apple Developer account\n";
        echo "2. Replace sample configuration with real Apple credentials\n";
        echo "3. Download and install your actual .p8 private key\n";
        echo "4. Test the OAuth flow from the frontend\n";
        echo "5. Monitor logs for any authentication issues\n\n";
        
        echo "🧪 Test OAuth Flow:\n";
        echo "1. Start servers: php artisan serve & npm run dev\n";
        echo "2. Visit: http://localhost:5175/login\n";
        echo "3. Click 'Continue with Apple'\n";
        echo "4. Complete Apple authentication\n";
        echo "5. Verify redirect to dashboard\n\n";
        
    } else {
        echo "❌ Apple OAuth configuration incomplete\n\n";
        
        echo "🔧 Required Actions:\n";
        if (empty($config['client_id'])) echo "- Set APPLE_CLIENT_ID in .env\n";
        if (empty($config['team_id'])) echo "- Set APPLE_TEAM_ID in .env\n";
        if (empty($config['key_id'])) echo "- Set APPLE_KEY_ID in .env\n";
        if (!file_exists($privateKeyPath)) echo "- Add Apple private key file\n";
        echo "\nSee APPLE_OAUTH_SETUP.md for detailed instructions\n\n";
    }
    
    echo "📚 Resources:\n";
    echo "- Setup Guide: APPLE_OAUTH_SETUP.md\n";
    echo "- Apple Developer Console: https://developer.apple.com/account/\n";
    echo "- Sign In with Apple Docs: https://developer.apple.com/sign-in-with-apple/\n";
    
} catch (Exception $e) {
    echo "❌ Test failed with error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n🍎 Apple OAuth test complete!\n";

?>