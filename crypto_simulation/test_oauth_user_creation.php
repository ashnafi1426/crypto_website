<?php

/**
 * Test OAuth User Creation and Admin Assignment
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "=== OAuth User Creation Test ===\n\n";

// Check if there are any OAuth users
echo "1. Checking existing OAuth users:\n";
$oauthUsers = User::whereIn('provider', ['google', 'apple'])->get();

if ($oauthUsers->count() > 0) {
    foreach ($oauthUsers as $user) {
        echo "   - {$user->name} ({$user->email}) - Provider: {$user->provider} - Admin: " . ($user->is_admin ? 'Yes' : 'No') . "\n";
    }
} else {
    echo "   No OAuth users found.\n";
}

echo "\n2. Creating a test OAuth user:\n";

// Create a test Google OAuth user
$testUser = User::create([
    'name' => 'Test Google User',
    'email' => 'test.google@example.com',
    'provider' => 'google',
    'provider_id' => 'google_test_123',
    'avatar' => 'https://example.com/avatar.jpg',
    'email_verified_at' => now(),
    'password' => null,
    'is_admin' => false,
]);

echo "   ✅ Created test user: {$testUser->name} (ID: {$testUser->id})\n";
echo "   - Email: {$testUser->email}\n";
echo "   - Provider: {$testUser->provider}\n";
echo "   - Is Admin: " . ($testUser->is_admin ? 'Yes' : 'No') . "\n";

echo "\n3. Making test user an admin:\n";
$testUser->update(['is_admin' => true]);
$testUser->refresh();

echo "   ✅ Updated user to admin status\n";
echo "   - Is Admin: " . ($testUser->is_admin ? 'Yes' : 'No') . "\n";

echo "\n4. Testing OAuth callback data structure:\n";
$callbackData = [
    'id' => $testUser->id,
    'name' => $testUser->name,
    'email' => $testUser->email,
    'avatar' => $testUser->avatar,
    'is_admin' => $testUser->is_admin,
];

echo "   OAuth callback data:\n";
echo "   " . json_encode($callbackData, JSON_PRETTY_PRINT) . "\n";

echo "\n5. Testing base64 encoding (like in OAuth callback):\n";
$encodedData = base64_encode(json_encode($callbackData));
echo "   Encoded: " . substr($encodedData, 0, 50) . "...\n";

$decodedData = json_decode(base64_decode($encodedData), true);
echo "   Decoded: " . json_encode($decodedData, JSON_PRETTY_PRINT) . "\n";

echo "\n6. Cleanup:\n";
$testUser->delete();
echo "   ✅ Test user deleted\n";

echo "\n=== Instructions for Making OAuth User Admin ===\n";
echo "To make an OAuth user an admin:\n\n";
echo "1. Find the user:\n";
echo "   php artisan tinker\n";
echo "   >>> \$user = User::where('email', 'user@example.com')->first();\n\n";
echo "2. Make them admin:\n";
echo "   >>> \$user->update(['is_admin' => true]);\n\n";
echo "3. Verify:\n";
echo "   >>> \$user->is_admin; // Should return true\n\n";

echo "=== Test Complete ===\n";