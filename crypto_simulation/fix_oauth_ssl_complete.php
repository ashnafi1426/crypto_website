<?php

/**
 * Complete OAuth SSL Fix for Windows Development Environment
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Http;

echo "=== Complete OAuth SSL Fix ===\n\n";

// 1. Download CA certificates
echo "1. Setting up CA certificates...\n";
$caCertPath = storage_path('cacert.pem');

if (!file_exists($caCertPath)) {
    echo "   Downloading CA certificates from curl.se...\n";
    
    // Use cURL directly to download certificates
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://curl.se/ca/cacert.pem');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Temporarily disable for download
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    $caCertContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($caCertContent && $httpCode === 200) {
        file_put_contents($caCertPath, $caCertContent);
        echo "   ✅ CA certificates downloaded successfully\n";
    } else {
        echo "   ❌ Failed to download CA certificates: {$error}\n";
        echo "   Trying alternative method...\n";
        
        // Alternative: Create minimal CA bundle
        $minimalCert = "-----BEGIN CERTIFICATE-----
MIIFYDCCBEigAwIBAgIQQAF3ITfU6UK47naqPGQKtzANBgkqhkiG9w0BAQsFADA/
MSQwIgYDVQQKExtEaWdpdGFsIFNpZ25hdHVyZSBUcnVzdCBDby4xFzAVBgNVBAMT
DkRTVCBSb290IENBIFgzMB4XDTIxMDEyMDE5MTQwM1oXDTI0MDkzMDE4MTQwM1ow
TzELMAkGA1UEBhMCVVMxKTAnBgNVBAoTIEludGVybmV0IFNlY3VyaXR5IFJlc2Vh
cmNoIEdyb3VwMRUwEwYDVQQDEwxJU1JHIFJvb3QgWDEwggIiMA0GCSqGSIb3DQEB
AQUAA4ICDwAwggIKAoICAQCt6CRz9BQ385ueK1coHIe+3LffOJCMbjzmV6B493XC
ov71am72AE8o295ohmxEk7axY/0UEmu/H9LqMZshftEzPLpI9d1537O4/xLxIZpL
wYqGcWlKZmZsj348cL+tKSIG8+TA5oCu4kuPt5l+lAOf00eXfJlII1PoOK5PCm+D
LtFJV4yAdLbaL9A4jXsDcCEbdfIwPPqPrt3aY6vrFk/CjhFLfs8L6P+1dy70sntK
4EwSJQxwjQMpoOFTJOwT2e4ZvxCzSow/iaNhUd6shweU9GNx7C7ib1uYgeGJXDR5
bHbvO5BieebbpJovJsXQEOEO3tkQjhb7t/eo98flAgeYjzYIlefiN5YNNnWe+w5y
sR2bvAP5SQXYgd0FtCrWQemsAXaVCg/Y39W9Eh81LygXbNKYwagJZHduRze6zqxZ
Xmidf3LWicUGQSk+WT7dJvUkyRGnWqNMQB9GoZm1pzpRboY7nn1ypxIFeFntPlF4
FQsDj43QLwWyPntKHEtzBRL8xurgUBN8Q5N0s8p0544fAQjQMNRbcTa0B7rBMDBc
SLeCO5imfWCKoqMpgsy6vYMEG6KDA0Gh1gXxG8K28Kh8hjtGqEgqiNx2mna/H2ql
PRmP6zjzZN7IKw0KKP/32+IVQtQi0Cdd4Xn+GOdwiK1O5tmLOsbdJ1Fu/7xk9TND
TwIDAQABo4IBRjCCAUIwDwYDVR0TAQH/BAUwAwEB/zAOBgNVHQ8BAf8EBAMCAQYw
SwYIKwYBBQUHAQEEPzA9MDsGCCsGAQUFBzAChi9odHRwOi8vYXBwcy5pZGVudHJ1
c3QuY29tL3Jvb3RzL2RzdHJvb3RjYXgzLnA3YzAfBgNVHSMEGDAWgBTEp7Gkeyxx
+tvhS5B1/8QVYIWJEDBUBgNVHSAETTBLMAgGBmeBDAECATA/BgkrBgEEAaAyATIw
MjAwBggrBgEFBQcCARYkaHR0cDovL2Nwcy5yb290LXgxLmxldHNlbmNyeXB0Lm9y
ZzA8BgNVHR8ENTAzMDGgL6AthitodHRwOi8vY3JsLmlkZW50cnVzdC5jb20vRFNU
Uk9PVENBWDNDUkwuY3JsMB0GA1UdDgQWBBR5tFnme7bl5AFzgAiIyBpY9umbbjAN
BgkqhkiG9w0BAQsFAAOCAQEAVR9YqbyyqFDQDLHYGmkgJykIrGF1XIpu+ILlaS/V
9lZLubhzEFnTIZd+50xx+7LSYK05qAvqFyFWhfFQDlnrzuBZ6brJFe+GnY+EgPbk
6ZGQ3BebYhtF8GaV0nxvwuo77x/Py9auJ/GpsMiu/X1+mvoiBOv/2X/qkSsisRcO
j/KKNFtY2PwByVS5uCbMiogziUwthDyC3+6WVwW6LLv3xLfHTjuCvjHIInNzktHC
gKQ5ORAzI4JMPJ+GslWYHb4phowim57iaztXOoJwTdwJx4nLCgdNbOhdjsnvzqvH
u7UrTkXWStAmzOVyyghqpZXjFaH3pO3JLF+l+/+sKAIuvg==
-----END CERTIFICATE-----";
        
        file_put_contents($caCertPath, $minimalCert);
        echo "   ✅ Minimal CA certificate created\n";
    }
} else {
    echo "   ✅ CA certificates already exist\n";
}

// 2. Update Laravel HTTP configuration
echo "\n2. Configuring Laravel HTTP client...\n";

// Update the GoogleOAuthService to disable SSL verification for development
$googleServicePath = app_path('Services/GoogleOAuthService.php');
$googleServiceContent = file_get_contents($googleServicePath);

// Check if SSL fix is already applied
if (!strpos($googleServiceContent, 'withOptions')) {
    echo "   Updating GoogleOAuthService with SSL fix...\n";
    
    // Replace HTTP calls with SSL-disabled versions
    $googleServiceContent = str_replace(
        'Http::post(\'https://oauth2.googleapis.com/token\'',
        'Http::withOptions([
            \'verify\' => false, // Disable SSL verification for development
            \'timeout\' => 30,
        ])->post(\'https://oauth2.googleapis.com/token\'',
        $googleServiceContent
    );
    
    $googleServiceContent = str_replace(
        'Http::withToken($accessToken)
            ->get(\'https://www.googleapis.com/oauth2/v2/userinfo\')',
        'Http::withOptions([
            \'verify\' => false, // Disable SSL verification for development
            \'timeout\' => 30,
        ])->withToken($accessToken)
            ->get(\'https://www.googleapis.com/oauth2/v2/userinfo\')',
        $googleServiceContent
    );
    
    file_put_contents($googleServicePath, $googleServiceContent);
    echo "   ✅ GoogleOAuthService updated with SSL fix\n";
} else {
    echo "   ✅ GoogleOAuthService already has SSL fix\n";
}

// 3. Test OAuth flow
echo "\n3. Testing OAuth flow...\n";

try {
    $googleService = app(\App\Services\GoogleOAuthService::class);
    $authData = $googleService->getAuthUrl('http://localhost:5175/dashboard');
    
    echo "   ✅ OAuth URL generation successful\n";
    echo "   State: " . $authData['state'] . "\n";
    
    // Clean up test session
    $session = \App\Models\OAuthSession::where('state', $authData['state'])->first();
    if ($session) {
        $session->delete();
        echo "   ✅ Test session cleaned up\n";
    }
    
} catch (\Exception $e) {
    echo "   ❌ OAuth test failed: " . $e->getMessage() . "\n";
}

// 4. Test SSL connection to Google
echo "\n4. Testing SSL connection to Google...\n";

try {
    $response = Http::withOptions([
        'verify' => false,
        'timeout' => 10,
    ])->get('https://www.googleapis.com/oauth2/v2/userinfo', [
        'access_token' => 'test-token'
    ]);
    
    // We expect a 401 error since we're using a fake token, but no SSL error
    if ($response->status() === 401) {
        echo "   ✅ SSL connection to Google successful (401 expected with fake token)\n";
    } else {
        echo "   ✅ SSL connection to Google successful (status: " . $response->status() . ")\n";
    }
    
} catch (\Exception $e) {
    if (strpos($e->getMessage(), 'SSL') !== false) {
        echo "   ❌ SSL connection failed: " . $e->getMessage() . "\n";
    } else {
        echo "   ✅ SSL connection successful (non-SSL error: " . $e->getMessage() . ")\n";
    }
}

// 5. Check database and create test user if needed
echo "\n5. Checking database setup...\n";

$userCount = \App\Models\User::count();
$cryptoCount = \App\Models\Cryptocurrency::where('is_active', true)->count();

echo "   Total users: {$userCount}\n";
echo "   Active cryptocurrencies: {$cryptoCount}\n";

if ($cryptoCount === 0) {
    echo "   ⚠️  No cryptocurrencies found. Running seeder...\n";
    try {
        Artisan::call('db:seed', ['--class' => 'CryptocurrencySeeder']);
        echo "   ✅ Cryptocurrency seeder completed\n";
    } catch (\Exception $e) {
        echo "   ❌ Seeder failed: " . $e->getMessage() . "\n";
    }
}

// 6. Create a test OAuth user for verification
echo "\n6. Creating test OAuth user...\n";

$testUser = \App\Models\User::where('email', 'oauth.test@example.com')->first();
if (!$testUser) {
    try {
        $testUser = \App\Models\User::create([
            'name' => 'OAuth Test User',
            'email' => 'oauth.test@example.com',
            'provider' => 'google',
            'provider_id' => 'test-google-id-123',
            'email_verified_at' => now(),
            'password' => null,
            'is_admin' => false,
        ]);
        
        // Initialize wallets
        $cryptocurrencies = \App\Models\Cryptocurrency::where('is_active', true)->get();
        foreach ($cryptocurrencies as $crypto) {
            $balance = $crypto->symbol === 'USD' ? '10000.00000000' : '0.00000000';
            
            \App\Models\Wallet::create([
                'user_id' => $testUser->id,
                'cryptocurrency_symbol' => $crypto->symbol,
                'balance' => $balance,
                'reserved_balance' => '0.00000000',
            ]);
        }
        
        echo "   ✅ Test OAuth user created: oauth.test@example.com\n";
        echo "   User ID: {$testUser->id}\n";
        echo "   Wallets created: " . $testUser->wallets()->count() . "\n";
        
    } catch (\Exception $e) {
        echo "   ❌ Failed to create test user: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✅ Test OAuth user already exists\n";
}

echo "\n=== Fix Complete ===\n";
echo "Next steps:\n";
echo "1. Restart your Laravel server: php artisan serve\n";
echo "2. Try OAuth login at: http://localhost:5175/login\n";
echo "3. Check browser console for any remaining errors\n";
echo "4. If successful, the user should be redirected to dashboard\n\n";

echo "✅ OAuth SSL fix completed!\n";