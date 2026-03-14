<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Registration Validation ===\n\n";

// Test different registration scenarios
$testCases = [
    [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123'
    ],
    [
        'name' => 'Test User 2',
        'email' => 'invalid-email',
        'password' => 'short',
        'password_confirmation' => 'different'
    ],
    [
        'name' => '',
        'email' => '',
        'password' => '',
        'password_confirmation' => ''
    ]
];

foreach ($testCases as $index => $testData) {
    echo "Test Case " . ($index + 1) . ":\n";
    echo "Data: " . json_encode($testData, JSON_PRETTY_PRINT) . "\n";
    
    // Test validation rules
    $validator = Validator::make($testData, [
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255',
        'password' => 'required|string|min:8',
        'password_confirmation' => 'required|string|same:password',
    ]);

    if ($validator->fails()) {
        echo "❌ Validation failed:\n";
        foreach ($validator->errors()->all() as $error) {
            echo "   - $error\n";
        }
        echo "Detailed errors:\n";
        foreach ($validator->errors()->toArray() as $field => $fieldErrors) {
            echo "   $field: " . implode(', ', $fieldErrors) . "\n";
        }
    } else {
        echo "✅ Validation passed\n";
        
        // Check if email already exists
        $existingUser = \App\Models\User::where('email', $testData['email'])->first();
        if ($existingUser) {
            echo "⚠️  Email already exists in database\n";
        }
    }
    
    echo "\n" . str_repeat('-', 50) . "\n\n";
}

// Test the actual registration endpoint
echo "=== Testing Registration Endpoint ===\n\n";

try {
    $authService = app(\App\Services\Contracts\AuthenticationServiceInterface::class);
    
    $testRegistration = [
        'name' => 'Test Registration User',
        'email' => 'test.registration@example.com',
        'password' => 'TestPassword123!',
        'password_confirmation' => 'TestPassword123!'
    ];
    
    echo "Testing registration with data:\n";
    echo json_encode($testRegistration, JSON_PRETTY_PRINT) . "\n\n";
    
    $result = $authService->register($testRegistration);
    
    if ($result['success']) {
        echo "✅ Registration successful!\n";
        echo "User ID: " . $result['user']['id'] . "\n";
        echo "Email: " . $result['user']['email'] . "\n";
    } else {
        echo "❌ Registration failed:\n";
        echo "Message: " . $result['message'] . "\n";
        if (isset($result['errors'])) {
            echo "Errors:\n";
            foreach ($result['errors'] as $field => $fieldErrors) {
                echo "   $field: " . (is_array($fieldErrors) ? implode(', ', $fieldErrors) : $fieldErrors) . "\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Exception during registration: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";