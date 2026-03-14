<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\AnalyticsService;

echo "=== Testing Analytics Service ===\n\n";

try {
    $analyticsService = new AnalyticsService();
    echo "✅ AnalyticsService instantiated successfully\n";
    
    $analytics = $analyticsService->getDashboardAnalytics();
    echo "✅ getDashboardAnalytics() executed successfully\n";
    echo "Analytics data keys: " . implode(', ', array_keys($analytics)) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error in AnalyticsService:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Testing Analytics API Endpoint ===\n";

// Test the actual API endpoint
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
curl_close($ch);

$loginResult = json_decode($response, true);
$token = $loginResult['token'];

// Test analytics endpoint
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/admin/analytics');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

if ($httpCode !== 200) {
    echo "\n❌ Analytics API endpoint is failing\n";
} else {
    echo "\n✅ Analytics API endpoint working\n";
}