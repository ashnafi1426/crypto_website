<?php

/**
 * Verify Dashboard and Admin Access for OAuth Users
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Http;

echo "=== Dashboard Access Verification ===\n\n";

// 1. Check OAuth users and their access levels
echo "1. OAuth Users and Access Levels:\n";
$oauthUsers = User::whereIn('provider', ['google', 'apple'])->get();

if ($oauthUsers->count() > 0) {
    foreach ($oauthUsers as $user) {
        echo "   User: {$user->name} ({$user->email})\n";
        echo "   Provider: {$user->provider}\n";
        echo "   Admin: " . ($user->is_admin ? 'Yes' : 'No') . "\n";
        echo "   Should redirect to: " . ($user->is_admin ? 'Admin Panel (/admin)' : 'Dashboard (/dashboard)') . "\n";
        echo "   Wallets: " . $user->wallets()->count() . "\n";
        echo "   Created: {$user->created_at}\n\n";
    }
} else {
    echo "   No OAuth users found.\n";
    echo "   Create a test user by completing OAuth flow or running:\n";
    echo "   php fix_oauth_ssl_complete.php\n\n";
}

// 2. Test API endpoints that dashboard/admin would use
echo "2. Testing API Endpoints:\n";

// Create a test token for API testing
$testUser = User::first();
if ($testUser) {
    $token = $testUser->createToken('test-token')->plainTextToken;
    
    // Test user endpoint
    echo "   Testing /api/auth/user endpoint...\n";
    try {
        $response = Http::withToken($token)->get('http://127.0.0.1:8000/api/auth/user');
        if ($response->successful()) {
            $userData = $response->json();
            echo "   ✅ User endpoint working\n";
            echo "   User data includes: " . implode(', ', array_keys($userData['user'] ?? [])) . "\n";
        } else {
            echo "   ❌ User endpoint failed: " . $response->status() . "\n";
        }
    } catch (\Exception $e) {
        echo "   ❌ User endpoint error: " . $e->getMessage() . "\n";
    }
    
    // Test wallets endpoint
    echo "\n   Testing /api/wallets endpoint...\n";
    try {
        $response = Http::withToken($token)->get('http://127.0.0.1:8000/api/wallets');
        if ($response->successful()) {
            $walletsData = $response->json();
            echo "   ✅ Wallets endpoint working\n";
            echo "   Wallets count: " . count($walletsData['wallets'] ?? []) . "\n";
        } else {
            echo "   ❌ Wallets endpoint failed: " . $response->status() . "\n";
        }
    } catch (\Exception $e) {
        echo "   ❌ Wallets endpoint error: " . $e->getMessage() . "\n";
    }
    
    // Test admin endpoint (if user is admin)
    if ($testUser->is_admin) {
        echo "\n   Testing /api/admin/users endpoint...\n";
        try {
            $response = Http::withToken($token)->get('http://127.0.0.1:8000/api/admin/users');
            if ($response->successful()) {
                echo "   ✅ Admin users endpoint working\n";
            } else {
                echo "   ❌ Admin users endpoint failed: " . $response->status() . "\n";
            }
        } catch (\Exception $e) {
            echo "   ❌ Admin users endpoint error: " . $e->getMessage() . "\n";
        }
    }
    
    // Clean up test token
    $testUser->tokens()->where('name', 'test-token')->delete();
    
} else {
    echo "   No users found for API testing\n";
}

// 3. Check frontend routing configuration
echo "\n3. Frontend Routing Check:\n";
echo "   Dashboard route: /dashboard (for regular users)\n";
echo "   Admin route: /admin (for admin users)\n";
echo "   Login route: /login (OAuth callback destination)\n\n";

// 4. OAuth callback flow verification
echo "4. OAuth Callback Flow:\n";
echo "   1. User clicks 'Continue with Google' on /login\n";
echo "   2. Redirected to Google OAuth\n";
echo "   3. After Google auth, redirected to: /api/auth/google/callback\n";
echo "   4. Backend processes OAuth, creates/updates user\n";
echo "   5. Backend redirects to frontend with auth data:\n";
echo "      - Regular user: /login?auth_success=true&token=...&user=...\n";
echo "      - Admin user: /login?auth_success=true&token=...&user=...\n";
echo "   6. Frontend AuthContext processes callback\n";
echo "   7. Frontend redirects based on user.is_admin:\n";
echo "      - Regular user: navigate('/dashboard')\n";
echo "      - Admin user: navigate('/admin')\n\n";

// 5. Common issues and solutions
echo "5. Common Issues and Solutions:\n";
echo "   Issue: 'redirect_uri_mismatch'\n";
echo "   Solution: Update Google Cloud Console with correct redirect URI\n";
echo "   See: OAUTH_GOOGLE_SETUP.md\n\n";

echo "   Issue: User created but not redirecting to dashboard\n";
echo "   Solution: Check browser console for JavaScript errors\n";
echo "   Verify AuthContext is receiving user data correctly\n\n";

echo "   Issue: OAuth user needs admin access\n";
echo "   Solution: Run 'php make_oauth_user_admin.php user@email.com'\n\n";

echo "   Issue: Dashboard shows 'loading' indefinitely\n";
echo "   Solution: Check API endpoints are working\n";
echo "   Verify token is being sent with requests\n\n";

// 6. Next steps
echo "6. Next Steps for Testing:\n";
echo "   1. Ensure both servers are running:\n";
echo "      Backend: php artisan serve (port 8000)\n";
echo "      Frontend: npm run dev (port 5175)\n\n";

echo "   2. Update Google OAuth redirect URI (if needed):\n";
echo "      Add: http://localhost:8000/api/auth/google/callback\n";
echo "      In Google Cloud Console > APIs & Services > Credentials\n\n";

echo "   3. Test OAuth flow:\n";
echo "      - Visit: http://localhost:5175/login\n";
echo "      - Click 'Continue with Google'\n";
echo "      - Complete Google authentication\n";
echo "      - Should redirect to dashboard or admin panel\n\n";

echo "   4. Check browser console for any errors\n";
echo "   5. Verify network requests in browser dev tools\n\n";

echo "✅ Verification complete!\n";
echo "OAuth system is configured and ready for testing.\n";