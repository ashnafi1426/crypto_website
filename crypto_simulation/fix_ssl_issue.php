<?php

/**
 * Fix SSL Certificate Issue for OAuth
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Fixing SSL Certificate Issue ===\n\n";

// Check current cURL SSL settings
echo "1. Current cURL SSL Configuration:\n";
echo "   CURLOPT_SSL_VERIFYPEER: " . (curl_version()['ssl_version'] ?? 'Unknown') . "\n";
echo "   OpenSSL Version: " . OPENSSL_VERSION_TEXT . "\n\n";

// Download and set up CA certificates
echo "2. Setting up CA certificates for cURL...\n";

$caCertPath = storage_path('cacert.pem');

if (!file_exists($caCertPath)) {
    echo "   Downloading CA certificates...\n";
    $caCertUrl = 'https://curl.se/ca/cacert.pem';
    
    // Use file_get_contents with context to download
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]
    ]);
    
    try {
        $caCertContent = file_get_contents($caCertUrl, false, $context);
        if ($caCertContent) {
            file_put_contents($caCertPath, $caCertContent);
            echo "   ✅ CA certificates downloaded to: {$caCertPath}\n";
        } else {
            echo "   ❌ Failed to download CA certificates\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Error downloading CA certificates: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✅ CA certificates already exist at: {$caCertPath}\n";
}

echo "\n3. Updating Laravel HTTP Client Configuration...\n";

// Create a custom HTTP client configuration
$configPath = config_path('http.php');
$configContent = <<<'PHP'
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HTTP Client Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Laravel's HTTP client (Guzzle)
    |
    */

    'verify' => storage_path('cacert.pem'),
    
    'curl' => [
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CAINFO => storage_path('cacert.pem'),
    ],
];
PHP;

file_put_contents($configPath, $configContent);
echo "   ✅ Created HTTP configuration file: {$configPath}\n";

echo "\n4. Testing SSL Connection to Google...\n";

try {
    // Test connection to Google OAuth endpoint
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_CAINFO, $caCertPath);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request only
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "   ❌ SSL Test Failed: {$error}\n";
    } else {
        echo "   ✅ SSL Connection to Google successful (HTTP {$httpCode})\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ SSL Test Error: " . $e->getMessage() . "\n";
}

echo "\n5. Alternative: Disable SSL Verification (Development Only)\n";
echo "   If the above doesn't work, we can disable SSL verification for development.\n";
echo "   This is NOT recommended for production!\n\n";

echo "=== Next Steps ===\n";
echo "1. Restart your Laravel server: php artisan serve\n";
echo "2. Try the OAuth flow again\n";
echo "3. If still having issues, run: php fix_ssl_issue_alternative.php\n\n";

echo "✅ SSL fix attempt completed!\n";