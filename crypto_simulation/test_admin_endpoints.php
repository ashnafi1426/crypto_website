<?php

// Test admin endpoints via HTTP
echo "=== Testing Admin Endpoints via HTTP ===\n\n";

$baseUrl = 'http://127.0.0.1:8000/api';

// Test admin login
echo "1. Testing admin login...\n";
$loginData = [
    'email' => 'admin@cryptoexchange.com',
    'password' => 'admin123'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/auth/login');
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
    $loginResponse = json_decode($response, true);
    if ($loginResponse['success']) {
        echo "✓ Admin login successful\n";
        echo "  User: " . $loginResponse['user']['name'] . "\n";
        echo "  Is Admin: " . ($loginResponse['user']['is_admin'] ? 'Yes' : 'No') . "\n\n";
        
        $token = $loginResponse['token'];
        
        // Test dashboard endpoint
        echo "2. Testing dashboard endpoint...\n";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/admin/dashboard');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $dashboardResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "  HTTP Code: $httpCode\n";
        if ($httpCode === 200) {
            $dashboardData = json_decode($dashboardResponse, true);
            if ($dashboardData['success']) {
                echo "✓ Dashboard data retrieved successfully\n";
                echo "  Total Users: " . $dashboardData['data']['stats']['total_users'] . "\n";
                echo "  Total Deposits: $" . number_format($dashboardData['data']['stats']['total_deposits'], 2) . "\n";
            } else {
                echo "✗ Dashboard failed: " . $dashboardData['message'] . "\n";
            }
        } else {
            echo "✗ Dashboard HTTP error: $httpCode\n";
            echo "  Response: " . substr($dashboardResponse, 0, 200) . "\n";
        }
        echo "\n";
        
        // Test analytics endpoint
        echo "3. Testing analytics endpoint...\n";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/admin/analytics');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $analyticsResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "  HTTP Code: $httpCode\n";
        if ($httpCode === 200) {
            $analyticsData = json_decode($analyticsResponse, true);
            if ($analyticsData['success']) {
                echo "✓ Analytics data retrieved successfully\n";
                if (isset($analyticsData['note'])) {
                    echo "  Note: " . $analyticsData['note'] . "\n";
                }
            } else {
                echo "✗ Analytics failed: " . $analyticsData['message'] . "\n";
            }
        } else {
            echo "✗ Analytics HTTP error: $httpCode\n";
            echo "  Response: " . substr($analyticsResponse, 0, 500) . "\n";
        }
        echo "\n";
        
        // Test users endpoint
        echo "4. Testing users endpoint...\n";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/admin/users');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $usersResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "  HTTP Code: $httpCode\n";
        if ($httpCode === 200) {
            $usersData = json_decode($usersResponse, true);
            if ($usersData['success']) {
                echo "✓ Users data retrieved successfully\n";
                echo "  Total Users: " . count($usersData['users']) . "\n";
                if (!empty($usersData['users'])) {
                    echo "  First User: " . $usersData['users'][0]['name'] . "\n";
                }
            } else {
                echo "✗ Users failed: " . $usersData['message'] . "\n";
            }
        } else {
            echo "✗ Users HTTP error: $httpCode\n";
            echo "  Response: " . substr($usersResponse, 0, 200) . "\n";
        }
        
    } else {
        echo "✗ Login failed: " . $loginResponse['message'] . "\n";
    }
} else {
    echo "✗ Login HTTP error: $httpCode\n";
    echo "  Response: " . substr($response, 0, 200) . "\n";
}

echo "\n=== Test Complete ===\n";