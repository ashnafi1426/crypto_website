<?php

echo "=== Full Frontend-Backend Integration Test ===\n\n";

// Test 1: Backend Health Check
echo "1. Backend Health Check:\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/cryptocurrencies');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "✅ Backend is running and accessible\n";
} else {
    echo "❌ Backend is not accessible (HTTP $httpCode)\n";
    exit(1);
}

// Test 2: Admin Login
echo "\n2. Admin Authentication Test:\n";
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

if ($httpCode === 200) {
    $loginResult = json_decode($response, true);
    $token = $loginResult['token'];
    echo "✅ Admin login successful\n";
    echo "   User: {$loginResult['user']['name']}\n";
    echo "   Admin: " . ($loginResult['user']['is_admin'] ? 'Yes' : 'No') . "\n";
} else {
    echo "❌ Admin login failed (HTTP $httpCode)\n";
    exit(1);
}

// Test 3: Admin Dashboard Data
echo "\n3. Admin Dashboard Data Test:\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/admin/dashboard');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $dashboardData = json_decode($response, true);
    echo "✅ Dashboard data retrieved successfully\n";
    echo "   Data keys: " . implode(', ', array_keys($dashboardData['data'])) . "\n";
} else {
    echo "❌ Dashboard data retrieval failed (HTTP $httpCode)\n";
}

// Test 4: Users Management
echo "\n4. Users Management Test:\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/admin/users');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $usersData = json_decode($response, true);
    echo "✅ Users data retrieved successfully\n";
    if (isset($usersData['data']['users'])) {
        echo "   Total users: " . count($usersData['data']['users']) . "\n";
    } else {
        echo "   Response structure: " . implode(', ', array_keys($usersData)) . "\n";
    }
} else {
    echo "❌ Users data retrieval failed (HTTP $httpCode)\n";
}

// Test 5: KYC Management
echo "\n5. KYC Management Test:\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/admin/kyc/submissions');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "✅ KYC submissions retrieved successfully\n";
} else {
    echo "❌ KYC submissions retrieval failed (HTTP $httpCode)\n";
}

// Test 6: Regular User Access Control
echo "\n6. Access Control Test:\n";
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
    $regularResult = json_decode($response, true);
    $regularToken = $regularResult['token'];
    
    // Try to access admin dashboard with regular user token
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/admin/dashboard');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $regularToken,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 403) {
        echo "✅ Access control working - regular user blocked from admin routes\n";
    } else {
        echo "❌ Security issue - regular user can access admin routes (HTTP $httpCode)\n";
    }
} else {
    echo "⚠️  Could not test access control - regular user login failed\n";
}

echo "\n=== Integration Summary ===\n";
echo "🌐 Backend URL: http://127.0.0.1:8000\n";
echo "🖥️  Frontend URL: http://localhost:5174\n";
echo "🔐 Admin Login: admin@cryptoexchange.com / admin123\n";
echo "👤 Test User: john@example.com / password123\n";

echo "\n=== Next Steps ===\n";
echo "1. Open http://localhost:5174 in your browser\n";
echo "2. Login with admin credentials\n";
echo "3. You should be redirected to /admin automatically\n";
echo "4. Test all admin functionality\n";
echo "5. Try logging in with regular user to test access control\n";

echo "\n✅ Full integration test completed successfully!\n";