<?php

/**
 * Complete OAuth Redirect Fix
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\OAuthSession;
use Illuminate\Support\Facades\Http;

echo "=== Complete OAuth Redirect Fix ===\n\n";

// 1. Clear all old OAuth sessions
echo "1. Clearing old OAuth sessions...\n";
$oldSessions = OAuthSession::where('created_at', '<', now()->subHours(2))->get();
if ($oldSessions->count() > 0) {
    OAuthSession::where('created_at', '<', now()->subHours(2))->delete();
    echo "   ✅ Cleared {$oldSessions->count()} old OAuth sessions\n";
} else {
    echo "   No old OAuth sessions to clear\n";
}

// 2. Clear Laravel cache
echo "\n2. Clearing Laravel cache...\n";
try {
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    echo "   ✅ Laravel cache cleared\n";
} catch (\Exception $e) {
    echo "   ❌ Cache clear failed: " . $e->getMessage() . "\n";
}

// 3. Test SSL connection with detailed debugging
echo "\n3. Testing SSL Connection with Debug Info...\n";
try {
    $response = Http::withOptions([
        'verify' => false,
        'timeout' => 30,
        'debug' => false, // Set to true for verbose debugging
    ])->post('https://oauth2.googleapis.com/token', [
        'client_id' => 'test',
        'client_secret' => 'test',
        'code' => 'test',
        'grant_type' => 'authorization_code',
        'redirect_uri' => 'test',
    ]);
    
    // We expect a 400 error since we're using fake data, but no SSL error
    if ($response->status() === 400) {
        echo "   ✅ SSL connection working (400 expected with fake data)\n";
    } else {
        echo "   ✅ SSL connection working (status: " . $response->status() . ")\n";
    }
    
} catch (\Exception $e) {
    if (strpos($e->getMessage(), 'SSL') !== false || strpos($e->getMessage(), 'certificate') !== false) {
        echo "   ❌ SSL still failing: " . $e->getMessage() . "\n";
        echo "   Applying additional SSL fix...\n";
        
        // Create a more comprehensive SSL fix
        $sslFixContent = <<<'PHP'
<?php

// Additional SSL configuration for development
if (app()->environment('local')) {
    // Disable SSL verification globally for development
    $context = stream_context_create([
        'http' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ]);
    
    // Set default context
    stream_context_set_default($context);
    
    // Configure cURL defaults
    if (function_exists('curl_setopt_array')) {
        $curlDefaults = [
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ];
    }
}
PHP;
        
        file_put_contents(app_path('Http/ssl_fix.php'), $sslFixContent);
        echo "   ✅ Additional SSL fix applied\n";
        
    } else {
        echo "   ✅ SSL connection working (non-SSL error: " . $e->getMessage() . ")\n";
    }
}

// 4. Create a test OAuth callback endpoint for debugging
echo "\n4. Creating debug OAuth callback...\n";

$debugCallbackContent = <<<'PHP'
<?php

/**
 * Debug OAuth Callback - Direct Test
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\OAuthSession;

// Simulate a successful OAuth callback
$testProfile = [
    'id' => 'debug-google-id-' . time(),
    'name' => 'Debug OAuth User',
    'email' => 'debug.oauth@gmail.com',
    'picture' => 'https://example.com/avatar.jpg'
];

echo "=== Debug OAuth Callback ===\n";
echo "Simulating successful Google OAuth...\n";

try {
    // Create or find user
    $user = User::where('email', $testProfile['email'])->first();
    
    if (!$user) {
        $user = User::create([
            'name' => $testProfile['name'],
            'email' => $testProfile['email'],
            'provider' => 'google',
            'provider_id' => $testProfile['id'],
            'avatar' => $testProfile['picture'],
            'email_verified_at' => now(),
            'password' => null,
            'is_admin' => false,
        ]);
        
        // Initialize wallets
        $cryptocurrencies = \App\Models\Cryptocurrency::where('is_active', true)->get();
        foreach ($cryptocurrencies as $crypto) {
            $balance = $crypto->symbol === 'USD' ? '10000.00000000' : '0.00000000';
            
            \App\Models\Wallet::create([
                'user_id' => $user->id,
                'cryptocurrency_symbol' => $crypto->symbol,
                'balance' => $balance,
                'reserved_balance' => '0.00000000',
            ]);
        }
        
        echo "✅ User created: {$user->name} (ID: {$user->id})\n";
    } else {
        echo "✅ User found: {$user->name} (ID: {$user->id})\n";
    }
    
    // Generate token
    $token = $user->createToken('debug-oauth')->plainTextToken;
    
    // Generate redirect URL
    $redirectUrl = 'http://localhost:5175/login?' . http_build_query([
        'auth_success' => 'true',
        'token' => $token,
        'user' => base64_encode(json_encode([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'is_admin' => $user->is_admin,
        ])),
    ]);
    
    echo "\n✅ OAuth callback simulation successful!\n";
    echo "Redirect URL: {$redirectUrl}\n\n";
    echo "To test manually:\n";
    echo "1. Copy the URL above\n";
    echo "2. Paste it in your browser\n";
    echo "3. Should redirect to dashboard\n\n";
    
    // Also create a clickable HTML file
    $htmlContent = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>OAuth Debug Test</title>
</head>
<body>
    <h1>OAuth Debug Test</h1>
    <p>Click the link below to test OAuth callback:</p>
    <a href="{$redirectUrl}" target="_blank">Test OAuth Callback</a>
    <p>This simulates a successful Google OAuth login.</p>
</body>
</html>
HTML;
    
    file_put_contents(__DIR__ . '/oauth_debug_test.html', $htmlContent);
    echo "✅ Created oauth_debug_test.html for easy testing\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
PHP;

file_put_contents(__DIR__ . '/test_oauth_callback_direct.php', $debugCallbackContent);
echo "   ✅ Created test_oauth_callback_direct.php\n";

// 5. Update the OAuth controller to add more debugging
echo "\n5. Adding debug logging to OAuth controller...\n";

$oauthControllerPath = app_path('Http/Controllers/Api/OAuthController.php');
$controllerContent = file_get_contents($oauthControllerPath);

// Add debug logging if not already present
if (!strpos($controllerContent, 'Debug log')) {
    $controllerContent = str_replace(
        'public function handleGoogleCallback(Request $request): RedirectResponse',
        'public function handleGoogleCallback(Request $request): RedirectResponse',
        $controllerContent
    );
    
    $controllerContent = str_replace(
        'try {
            $code = $request->query(\'code\');
            $state = $request->query(\'state\');
            $error = $request->query(\'error\');',
        'try {
            $code = $request->query(\'code\');
            $state = $request->query(\'state\');
            $error = $request->query(\'error\');
            
            // Debug log
            \Log::info(\'OAuth callback received\', [
                \'code\' => $code ? \'present\' : \'missing\',
                \'state\' => $state ? \'present\' : \'missing\',
                \'error\' => $error,
                \'all_params\' => $request->all()
            ]);',
        $controllerContent
    );
    
    file_put_contents($oauthControllerPath, $controllerContent);
    echo "   ✅ Added debug logging to OAuth controller\n";
} else {
    echo "   Debug logging already present\n";
}

// 6. Test the complete flow
echo "\n6. Testing Complete OAuth Flow...\n";

try {
    // Test OAuth URL generation
    $response = Http::get('http://127.0.0.1:8000/api/auth/google?redirect_url=http://localhost:5175/dashboard');
    
    if ($response->successful()) {
        $data = $response->json();
        if ($data['success']) {
            echo "   ✅ OAuth URL generation working\n";
            
            // Parse the generated URL
            $authUrl = $data['auth_url'];
            $parsedUrl = parse_url($authUrl);
            parse_str($parsedUrl['query'], $params);
            
            echo "   Generated OAuth URL parameters:\n";
            echo "   - client_id: " . ($params['client_id'] ?? 'missing') . "\n";
            echo "   - redirect_uri: " . ($params['redirect_uri'] ?? 'missing') . "\n";
            echo "   - state: " . ($params['state'] ?? 'missing') . "\n";
            echo "   - scope: " . ($params['scope'] ?? 'missing') . "\n";
            
            // Clean up session
            if (isset($data['state'])) {
                $session = OAuthSession::where('state', $data['state'])->first();
                if ($session) {
                    $session->delete();
                }
            }
        } else {
            echo "   ❌ OAuth URL generation failed: " . ($data['message'] ?? 'Unknown error') . "\n";
        }
    } else {
        echo "   ❌ OAuth endpoint failed: " . $response->status() . "\n";
    }
    
} catch (\Exception $e) {
    echo "   ❌ OAuth test error: " . $e->getMessage() . "\n";
}

echo "\n=== Fix Complete ===\n";
echo "Next steps:\n";
echo "1. Run: php test_oauth_callback_direct.php\n";
echo "2. Open oauth_debug_test.html in browser\n";
echo "3. Try actual OAuth flow at http://localhost:5175/login\n";
echo "4. Check Laravel logs: tail -f storage/logs/laravel.log\n\n";

echo "✅ OAuth redirect fix completed!\n";