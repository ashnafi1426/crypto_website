<?php

echo "=== Testing Admin Login Response Structure ===\n\n";

// Test admin login and check response structure
$loginData = [
    'email' => 'admin@cryptoexchange.com',
    'password' => 'admin123'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/auth/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

if ($httpCode === 200) {
    $loginResult = json_decode($response, true);
    
    echo "✅ Login successful!\n";
    echo "Response structure:\n";
    echo "- success: " . ($loginResult['success'] ? 'true' : 'false') . "\n";
    echo "- message: " . ($loginResult['message'] ?? 'N/A') . "\n";
    echo "- token: " . (isset($loginResult['token']) ? 'Present' : 'Missing') . "\n";
    echo "- user: " . (isset($loginResult['user']) ? 'Present' : 'Missing') . "\n";
    
    if (isset($loginResult['user'])) {
        echo "\nUser details:\n";
        echo "- id: " . ($loginResult['user']['id'] ?? 'N/A') . "\n";
        echo "- name: " . ($loginResult['user']['name'] ?? 'N/A') . "\n";
        echo "- email: " . ($loginResult['user']['email'] ?? 'N/A') . "\n";
        echo "- is_admin: " . ($loginResult['user']['is_admin'] ? 'true' : 'false') . "\n";
        
        if ($loginResult['user']['is_admin']) {
            echo "\n✅ User is confirmed as admin - frontend should redirect to /admin\n";
        } else {
            echo "\n❌ User is not admin - this is unexpected!\n";
        }
    } else {
        echo "\n❌ User data missing from response!\n";
    }
} else {
    echo "❌ Login failed!\n";
}

echo "\n=== Testing Regular User Login ===\n";

$regularLoginData = [
    'email' => 'john@example.com',
    'password' => 'password123'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/auth/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($regularLoginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $loginResult = json_decode($response, true);
    
    echo "✅ Regular user login successful!\n";
    if (isset($loginResult['user'])) {
        echo "- is_admin: " . ($loginResult['user']['is_admin'] ? 'true' : 'false') . "\n";
        
        if (!$loginResult['user']['is_admin']) {
            echo "✅ Regular user confirmed - frontend should redirect to /dashboard\n";
        } else {
            echo "❌ Regular user showing as admin - this is unexpected!\n";
        }
    }
} else {
    echo "❌ Regular user login failed!\n";
}

echo "\n=== Frontend Integration Instructions ===\n";
echo "1. The backend returns user.is_admin in the login response\n";
echo "2. Frontend should check result.user.is_admin immediately after login\n";
echo "3. If true, redirect to /admin\n";
echo "4. If false, redirect to /dashboard\n";
echo "5. No setTimeout needed - user data is available immediately\n";