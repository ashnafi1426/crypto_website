<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Authentication Fix ===\n\n";

// Test 1: Check if users exist
echo "1. Checking if test users exist...\n";
$users = \App\Models\User::whereIn('email', ['ashenafi14262@gmail.com', 'ashenafisileshi7@gmail.com'])->get();
foreach ($users as $user) {
    echo "   - User: {$user->email} (ID: {$user->id})\n";
}

if ($users->count() === 0) {
    echo "   No test users found. Creating them...\n";
    
    $user1 = \App\Models\User::create([
        'name' => 'Ashenafi Test 1',
        'email' => 'ashenafi14262@gmail.com',
        'password' => \Illuminate\Support\Facades\Hash::make('password123'),
        'email_verified_at' => now(),
    ]);
    echo "   - Created user: {$user1->email}\n";
    
    $user2 = \App\Models\User::create([
        'name' => 'Ashenafi Test 2', 
        'email' => 'ashenafisileshi7@gmail.com',
        'password' => \Illuminate\Support\Facades\Hash::make('password123'),
        'email_verified_at' => now(),
    ]);
    echo "   - Created user: {$user2->email}\n";
}

// Test 2: Test authentication service
echo "\n2. Testing authentication service...\n";
try {
    $authService = app(\App\Services\Contracts\AuthenticationServiceInterface::class);
    
    $result = $authService->authenticate('ashenafi14262@gmail.com', 'password123');
    
    if ($result['success']) {
        echo "   ✓ Authentication successful!\n";
        echo "   - Token: " . substr($result['token'], 0, 20) . "...\n";
        echo "   - User: {$result['user']['name']} ({$result['user']['email']})\n";
    } else {
        echo "   ✗ Authentication failed: {$result['message']}\n";
    }
} catch (Exception $e) {
    echo "   ✗ Authentication error: " . $e->getMessage() . "\n";
}

// Test 3: Test token validation
echo "\n3. Testing token validation...\n";
try {
    $user = \App\Models\User::where('email', 'ashenafi14262@gmail.com')->first();
    if ($user) {
        // Create a token
        $token = $user->createToken('test_token')->plainTextToken;
        echo "   - Created token: " . substr($token, 0, 20) . "...\n";
        
        // Test token validation
        $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        if ($tokenModel && $tokenModel->tokenable_id === $user->id) {
            echo "   ✓ Token validation successful!\n";
        } else {
            echo "   ✗ Token validation failed!\n";
        }
    }
} catch (Exception $e) {
    echo "   ✗ Token validation error: " . $e->getMessage() . "\n";
}

// Test 4: Check Sanctum configuration
echo "\n4. Checking Sanctum configuration...\n";
$statefulDomains = config('sanctum.stateful');
echo "   - Stateful domains: " . implode(', ', $statefulDomains) . "\n";

if (in_array('localhost:5173', $statefulDomains) || in_array('127.0.0.1:5173', $statefulDomains)) {
    echo "   ⚠ WARNING: Frontend domains still in stateful list!\n";
} else {
    echo "   ✓ Frontend domains removed from stateful list\n";
}

echo "\n=== Test Complete ===\n";