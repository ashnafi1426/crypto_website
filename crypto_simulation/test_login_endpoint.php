<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

use Illuminate\Http\Request;

echo "=== Testing Login Endpoint ===\n\n";

// Create a test request with JSON body
$data = [
    'email' => 'test@example.com',
    'password' => 'Test@123456'
];

$request = Request::create(
    '/api/auth/login',
    'POST',
    [], // parameters
    [], // cookies
    [], // files
    [], // server
    json_encode($data) // content
);

$request->headers->set('Content-Type', 'application/json');
$request->headers->set('Accept', 'application/json');

echo "Request Details:\n";
echo "  URL: /api/auth/login\n";
echo "  Method: POST\n";
echo "  Email: test@example.com\n";
echo "  Password: Test@123456\n\n";

try {
    $response = $kernel->handle($request);
    
    echo "Response Status: {$response->getStatusCode()}\n";
    echo "Response Body:\n";
    echo $response->getContent() . "\n\n";
    
    $data = json_decode($response->getContent(), true);
    
    if ($response->getStatusCode() === 200 && isset($data['success']) && $data['success']) {
        echo "✅ Login successful!\n";
        echo "Token: " . substr($data['token'], 0, 20) . "...\n";
        echo "User: {$data['user']['name']} ({$data['user']['email']})\n";
    } else {
        echo "❌ Login failed!\n";
        echo "Message: " . ($data['message'] ?? 'Unknown error') . "\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Exception occurred:\n";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

$kernel->terminate($request, $response ?? null);
