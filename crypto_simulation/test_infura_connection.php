<?php

require_once __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;

echo "🔗 Testing Infura Connection with PHP\n";
echo "===================================\n\n";

$nodeUrl = 'https://mainnet.infura.io/v3/38d8c48e9ee74a47bda0006e7e95fbe4';

try {
    // Test 1: Basic Guzzle client
    echo "1. Testing basic Guzzle HTTP client...\n";
    
    $client = new Client([
        'timeout' => 30,
        'verify' => false, // Disable SSL verification for testing
        'headers' => [
            'Content-Type' => 'application/json',
        ]
    ]);
    
    $payload = [
        'jsonrpc' => '2.0',
        'method' => 'eth_blockNumber',
        'params' => [],
        'id' => 1
    ];
    
    echo "   Sending request to: {$nodeUrl}\n";
    echo "   Payload: " . json_encode($payload) . "\n";
    
    $response = $client->post($nodeUrl, [
        'json' => $payload
    ]);
    
    $statusCode = $response->getStatusCode();
    $body = $response->getBody()->getContents();
    
    echo "   ✅ Response Status: {$statusCode}\n";
    echo "   ✅ Response Body: {$body}\n";
    
    $data = json_decode($body, true);
    
    if (isset($data['result'])) {
        $blockHex = $data['result'];
        $blockDecimal = hexdec($blockHex);
        echo "   ✅ Current Block: {$blockHex} (decimal: {$blockDecimal})\n";
    }
    
    echo "\n";
    
    // Test 2: Test with SSL verification enabled
    echo "2. Testing with SSL verification enabled...\n";
    
    $clientSSL = new Client([
        'timeout' => 30,
        'verify' => true, // Enable SSL verification
        'headers' => [
            'Content-Type' => 'application/json',
        ]
    ]);
    
    $responseSSL = $clientSSL->post($nodeUrl, [
        'json' => $payload
    ]);
    
    echo "   ✅ SSL verification works fine\n";
    echo "   ✅ Response Status: " . $responseSSL->getStatusCode() . "\n";
    
    echo "\n";
    
    // Test 3: Test different methods
    echo "3. Testing different Ethereum methods...\n";
    
    $methods = [
        'eth_blockNumber' => [],
        'eth_chainId' => [],
        'net_version' => []
    ];
    
    foreach ($methods as $method => $params) {
        try {
            $testPayload = [
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
                'id' => 1
            ];
            
            $testResponse = $client->post($nodeUrl, [
                'json' => $testPayload
            ]);
            
            $testData = json_decode($testResponse->getBody()->getContents(), true);
            
            if (isset($testData['result'])) {
                echo "   ✅ {$method}: {$testData['result']}\n";
            } else {
                echo "   ❌ {$method}: No result\n";
            }
            
        } catch (Exception $e) {
            echo "   ❌ {$method}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n";
    echo "🎉 Infura connection test successful!\n";
    echo "The API key is working correctly.\n\n";
    
} catch (Exception $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
    echo "Error details:\n";
    echo "   Class: " . get_class($e) . "\n";
    echo "   Code: " . $e->getCode() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    
    if (method_exists($e, 'getResponse') && $e->getResponse()) {
        echo "   Response Status: " . $e->getResponse()->getStatusCode() . "\n";
        echo "   Response Body: " . $e->getResponse()->getBody()->getContents() . "\n";
    }
}