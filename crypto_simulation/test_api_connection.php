<?php

require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "Testing API Connection...\n";

// Test basic API endpoint
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/health');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Health endpoint response: HTTP $httpCode\n";
echo "Response: $response\n\n";

// Test login to get a token
echo "Testing login...\n";

$loginData = [
    'email' => 'admin@cryptoexchange.com',
    'password' => 'password123'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/auth/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

$loginResponse = curl_exec($ch);
$loginHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Login response: HTTP $loginHttpCode\n";
echo "Response: $loginResponse\n\n";

$loginData = json_decode($loginResponse, true);

if ($loginData && isset($loginData['token'])) {
    $token = $loginData['token'];
    echo "Login successful! Token: " . substr($token, 0, 20) . "...\n\n";
    
    // Test deposits endpoint with token
    echo "Testing deposits endpoint with authentication...\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/deposits');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);

    $depositsResponse = curl_exec($ch);
    $depositsHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Deposits endpoint response: HTTP $depositsHttpCode\n";
    echo "Response: " . substr($depositsResponse, 0, 200) . "...\n\n";
    
    // Test address generation
    echo "Testing address generation...\n";
    
    $addressData = ['currency' => 'BTC'];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/deposits/generate-address');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($addressData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);

    $addressResponse = curl_exec($ch);
    $addressHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Address generation response: HTTP $addressHttpCode\n";
    echo "Response: $addressResponse\n";
    
} else {
    echo "Login failed. Cannot test authenticated endpoints.\n";
}

echo "\nAPI connection test completed.\n";