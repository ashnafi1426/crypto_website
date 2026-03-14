<?php

echo "=== Testing Dashboard API ===\n\n";

// Login as admin
$loginData = ['email' => 'admin@cryptoexchange.com', 'password' => 'admin123'];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/auth/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$loginResult = json_decode($response, true);
$token = $loginResult['token'];

// Test dashboard endpoint
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/admin/dashboard');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token, 'Accept: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Dashboard API Response:\n";
echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "✅ Dashboard API working\n";
    echo "Response structure: " . implode(', ', array_keys($data)) . "\n";
    if (isset($data['data'])) {
        echo "Data keys: " . implode(', ', array_keys($data['data'])) . "\n";
    }
} else {
    echo "❌ Dashboard API failed\n";
}