<?php

/**
 * Apple OAuth Setup Script
 * This script helps configure Apple Sign-In for the crypto exchange application
 */

echo "🍎 Apple OAuth Setup for Crypto Exchange\n";
echo "========================================\n\n";

// Check if required packages are installed
echo "1. Checking required packages...\n";

$composerJson = json_decode(file_get_contents(__DIR__ . '/composer.json'), true);
$requiredPackages = [
    'firebase/php-jwt' => 'JWT token handling',
    'phpseclib/phpseclib' => 'Cryptographic operations'
];

$missingPackages = [];
foreach ($requiredPackages as $package => $description) {
    if (!isset($composerJson['require'][$package])) {
        $missingPackages[] = $package;
        echo "   ❌ Missing: $package ($description)\n";
    } else {
        echo "   ✅ Found: $package\n";
    }
}

if (!empty($missingPackages)) {
    echo "\n📦 Install missing packages:\n";
    echo "   composer require " . implode(' ', $missingPackages) . "\n\n";
}

// Check Apple OAuth configuration
echo "2. Checking Apple OAuth configuration...\n";

$envFile = __DIR__ . '/.env';
$envContent = file_get_contents($envFile);

$appleConfig = [
    'APPLE_CLIENT_ID' => 'Apple Service ID (e.g., com.yourcompany.yourapp)',
    'APPLE_TEAM_ID' => 'Apple Developer Team ID (10 characters)',
    'APPLE_KEY_ID' => 'Apple Key ID (10 characters)',
    'APPLE_PRIVATE_KEY_PATH' => 'Path to Apple private key file (.p8)',
    'APPLE_REDIRECT_URI' => 'OAuth callback URL'
];

$needsConfiguration = [];
foreach ($appleConfig as $key => $description) {
    if (preg_match("/^$key=(.*)$/m", $envContent, $matches)) {
        $value = trim($matches[1]);
        if (empty($value) || strpos($value, 'your-') === 0) {
            $needsConfiguration[] = $key;
            echo "   ❌ $key: Not configured ($description)\n";
        } else {
            echo "   ✅ $key: Configured\n";
        }
    } else {
        $needsConfiguration[] = $key;
        echo "   ❌ $key: Missing from .env\n";
    }
}

// Check private key file
echo "\n3. Checking Apple private key file...\n";
$privateKeyPath = storage_path('apple-private-key.p8');
if (file_exists($privateKeyPath)) {
    echo "   ✅ Private key file found: $privateKeyPath\n";
    
    // Validate key format
    $keyContent = file_get_contents($privateKeyPath);
    if (strpos($keyContent, '-----BEGIN PRIVATE KEY-----') !== false) {
        echo "   ✅ Private key format appears valid\n";
    } else {
        echo "   ❌ Private key format may be invalid\n";
    }
} else {
    echo "   ❌ Private key file not found: $privateKeyPath\n";
    echo "      Download your .p8 key from Apple Developer Console\n";
}

// Check database migration
echo "\n4. Checking database schema...\n";
try {
    $pdo = new PDO('sqlite:' . database_path('database.sqlite'));
    
    // Check if social login fields exist
    $stmt = $pdo->query("PRAGMA table_info(users)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $socialFields = ['provider', 'provider_id'];
    $existingFields = array_column($columns, 'name');
    
    foreach ($socialFields as $field) {
        if (in_array($field, $existingFields)) {
            echo "   ✅ Database field '$field' exists\n";
        } else {
            echo "   ❌ Database field '$field' missing\n";
            echo "      Run: php artisan migrate\n";
        }
    }
    
} catch (Exception $e) {
    echo "   ❌ Database check failed: " . $e->getMessage() . "\n";
}

// Generate sample configuration
if (!empty($needsConfiguration)) {
    echo "\n📝 Sample Apple OAuth Configuration:\n";
    echo "=====================================\n";
    echo "Add these to your .env file:\n\n";
    
    $sampleConfig = [
        'APPLE_CLIENT_ID' => 'com.nexus.cryptoexchange',
        'APPLE_TEAM_ID' => 'ABCD123456',
        'APPLE_KEY_ID' => 'XYZ9876543',
        'APPLE_PRIVATE_KEY_PATH' => 'apple-private-key.p8',
        'APPLE_REDIRECT_URI' => 'http://localhost:8000/api/auth/apple/callback'
    ];
    
    foreach ($sampleConfig as $key => $value) {
        if (in_array($key, $needsConfiguration)) {
            echo "$key=$value\n";
        }
    }
}

// Setup instructions
echo "\n🛠️  Apple Developer Console Setup Instructions:\n";
echo "==============================================\n";
echo "1. Go to https://developer.apple.com/account/\n";
echo "2. Navigate to 'Certificates, Identifiers & Profiles'\n";
echo "3. Create an App ID (if not exists):\n";
echo "   - Description: NEXUS Crypto Exchange\n";
echo "   - Bundle ID: com.nexus.cryptoexchange\n";
echo "   - Enable 'Sign In with Apple'\n\n";
echo "4. Create a Services ID:\n";
echo "   - Description: NEXUS Web Service\n";
echo "   - Identifier: com.nexus.cryptoexchange.web\n";
echo "   - Enable 'Sign In with Apple'\n";
echo "   - Configure domains: localhost, your-domain.com\n";
echo "   - Return URLs: http://localhost:8000/api/auth/apple/callback\n\n";
echo "5. Create a Private Key:\n";
echo "   - Key Name: NEXUS Apple Sign In Key\n";
echo "   - Enable 'Sign In with Apple'\n";
echo "   - Download the .p8 file\n";
echo "   - Save as storage/apple-private-key.p8\n\n";
echo "6. Update .env with your actual values\n\n";

// Test configuration
echo "🧪 Test Apple OAuth Configuration:\n";
echo "==================================\n";
echo "After setup, test with:\n";
echo "1. php artisan serve\n";
echo "2. Visit: http://localhost:8000/api/auth/providers\n";
echo "3. Check if Apple provider is enabled\n";
echo "4. Test OAuth flow from frontend\n\n";

// Security notes
echo "🔒 Security Notes:\n";
echo "==================\n";
echo "1. Keep your .p8 private key secure\n";
echo "2. Never commit private keys to version control\n";
echo "3. Use environment variables for all sensitive data\n";
echo "4. Enable HTTPS in production\n";
echo "5. Validate redirect URIs in production\n\n";

echo "✅ Apple OAuth setup guide complete!\n";
echo "Follow the instructions above to configure Apple Sign-In.\n";

?>