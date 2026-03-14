<?php

/**
 * Complete OAuth Callback Test with Real User Data
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\OAuthSession;
use App\Services\GoogleOAuthService;

echo "=== Complete OAuth Callback Test ===\n\n";

// 1. Find your user
$user = User::where('email', 'ashenafi14264@gmail.com')->first();
if (!$user) {
    echo "❌ User not found\n";
    exit(1);
}

echo "Testing with user: {$user->name} ({$user->email})\n";
echo "User ID: {$user->id}\n";
echo "Provider: {$user->provider}\n";
echo "Admin: " . ($user->is_admin ? 'Yes' : 'No') . "\n\n";

// 2. Create a test OAuth session
$session = OAuthSession::create([
    'state' => 'test-callback-' . time(),
    'provider' => 'google',
    'redirect_url' => 'http://localhost:5175/dashboard',
    'expires_at' => now()->addMinutes(10),
]);

echo "Created test OAuth session: {$session->state}\n\n";

// 3. Simulate the complete OAuth callback process
echo "Simulating OAuth callback process...\n";

try {
    $googleService = app(GoogleOAuthService::class);
    
    // Simulate Google profile data (using your actual data)
    $mockProfile = [
        'id' => $user->provider_id ?: 'google-' . $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'picture' => $user->avatar ?: 'https://example.com/avatar.jpg'
    ];
    
    echo "Mock Google profile:\n";
    echo json_encode($mockProfile, JSON_PRETTY_PRINT) . "\n\n";
    
    // Test the callback handling logic manually
    echo "Testing callback handling...\n";
    
    // Update user (simulate what the service would do)
    $user->update([
        'name' => $mockProfile['name'],
        'avatar' => $mockProfile['picture'],
        'email_verified_at' => now(),
    ]);
    
    echo "✅ User updated\n";
    
    // Generate token
    $token = $user->createToken('oauth-callback-test-' . time())->plainTextToken;
    echo "✅ Token generated: " . substr($token, 0, 40) . "...\n";
    
    // Test the token
    $response = \Illuminate\Support\Facades\Http::withToken($token)
        ->timeout(10)
        ->get('http://127.0.0.1:8000/api/auth/user');
    
    if ($response->successful()) {
        $userData = $response->json();
        echo "✅ Token validation successful\n";
        echo "API returned user data:\n";
        echo json_encode($userData['user'], JSON_PRETTY_PRINT) . "\n\n";
    } else {
        echo "❌ Token validation failed: " . $response->status() . "\n";
        echo "Response: " . $response->body() . "\n\n";
    }
    
    // 4. Generate the exact callback URL that would be sent to frontend
    echo "Generating callback URL...\n";
    
    $callbackUserData = [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'avatar' => $user->avatar,
        'is_admin' => $user->is_admin,
    ];
    
    $callbackUrl = config('services.frontend.url') . '/login?' . http_build_query([
        'auth_success' => 'true',
        'token' => $token,
        'user' => base64_encode(json_encode($callbackUserData)),
    ]);
    
    echo "=== CALLBACK URL FOR TESTING ===\n";
    echo $callbackUrl . "\n\n";
    
    // 5. Also generate URL for debug tool
    $debugUrl = config('services.frontend.url') . '/debug_oauth_live.html?' . http_build_query([
        'auth_success' => 'true',
        'token' => $token,
        'user' => base64_encode(json_encode($callbackUserData)),
    ]);
    
    echo "=== DEBUG TOOL URL ===\n";
    echo $debugUrl . "\n\n";
    
    // 6. Test the actual OAuth controller
    echo "Testing OAuth controller callback...\n";
    
    // Simulate a callback request
    $mockCode = 'test-auth-code-' . time();
    $mockState = $session->state;
    
    // We can't easily test the full OAuth flow without Google, but we can test the URL generation
    $authData = $googleService->getAuthUrl('http://localhost:5175/dashboard');
    echo "✅ OAuth URL generation works\n";
    echo "Generated state: {$authData['state']}\n";
    
    // Clean up test session
    OAuthSession::where('state', $authData['state'])->delete();
    
    echo "\n=== TESTING INSTRUCTIONS ===\n";
    echo "1. Copy the CALLBACK URL above\n";
    echo "2. Paste it in your browser\n";
    echo "3. You should be redirected to the dashboard\n";
    echo "4. If not, copy the DEBUG TOOL URL to see what's happening\n\n";
    
    echo "5. Alternative: Test real OAuth flow:\n";
    echo "   - Visit: http://localhost:5175/login\n";
    echo "   - Click 'Continue with Google'\n";
    echo "   - Complete Google authentication\n";
    echo "   - Watch browser console for errors\n\n";
    
    echo "6. Debug with live tool:\n";
    echo "   - Visit: http://localhost:5175/debug_oauth_live.html\n";
    echo "   - Click 'Start Google OAuth' to test\n";
    echo "   - Or click 'Test Direct Callback' to simulate\n\n";
    
    // Clean up
    $session->delete();
    echo "✅ Test session cleaned up\n";
    
} catch (\Exception $e) {
    echo "❌ OAuth callback test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== NEXT STEPS ===\n";
echo "1. Test the callback URL in your browser\n";
echo "2. Check browser console for JavaScript errors\n";
echo "3. Use the debug tool to monitor the OAuth flow\n";
echo "4. If still having issues, check:\n";
echo "   - CORS configuration (now updated)\n";
echo "   - Frontend AuthContext handling\n";
echo "   - React Router configuration\n\n";

echo "✅ Complete OAuth callback test finished!\n";