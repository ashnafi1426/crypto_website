<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

use Illuminate\Http\Request;

echo "=== Testing Password Reset Endpoint ===\n\n";

// Create a test request
$request = Request::create('/api/auth/password/reset/request', 'POST', [], [], [], [], json_encode([
    'email' => 'ashenafi14262@gmail.com'
]));

$request->headers->set('Content-Type', 'application/json');
$request->headers->set('Accept', 'application/json');

echo "Request Details:\n";
echo "  URL: /api/auth/password/reset/request\n";
echo "  Method: POST\n";
echo "  Email: ashenafi14262@gmail.com\n\n";

try {
    $response = $kernel->handle($request);
    
    echo "Response Status: {$response->getStatusCode()}\n";
    echo "Response Body:\n";
    echo $response->getContent() . "\n\n";
    
    $data = json_decode($response->getContent(), true);
    
    if ($response->getStatusCode() === 200 && isset($data['success']) && $data['success']) {
        echo "✅ Password reset request successful!\n";
        if (isset($data['reset_url'])) {
            echo "Reset URL: {$data['reset_url']}\n";
        }
        if (isset($data['token'])) {
            echo "Token: {$data['token']}\n";
        }
    } else {
        echo "❌ Password reset request failed!\n";
        echo "Message: " . ($data['message'] ?? 'Unknown error') . "\n";
        if (isset($data['errors'])) {
            echo "Errors: " . json_encode($data['errors']) . "\n";
        }
    }
    
} catch (\Exception $e) {
    echo "❌ Exception occurred:\n";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

$kernel->terminate($request, $response ?? null);