<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Services\WalletAddressService;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\DepositController;

// Initialize Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Testing Remove MetaMask Address API\n";
echo "=====================================\n\n";

try {
    // Find a test user
    $testUser = User::first();
    
    if (!$testUser) {
        echo "❌ No test user found\n";
        exit(1);
    }
    
    echo "✅ Using test user: {$testUser->email}\n\n";
    
    // Create a MetaMask address for testing
    $walletService = new WalletAddressService();
    $testAddress = '0x742d35Cc6634C0532925a3b8D4C2C4e07C8B8C8B';
    
    echo "1. Creating MetaMask address for testing...\n";
    $depositAddress = $walletService->storeMetaMaskAddress($testUser, 'ETH', 'Ethereum', $testAddress);
    echo "   ✅ MetaMask address created: {$depositAddress->address}\n\n";
    
    // Test the API endpoint
    echo "2. Testing remove MetaMask address API...\n";
    
    // Create a mock request
    $request = new Request();
    $request->merge([
        'currency' => 'ETH',
        'network' => 'Ethereum'
    ]);
    
    // Mock the authenticated user
    $request->setUserResolver(function () use ($testUser) {
        return $testUser;
    });
    
    // Create controller instance
    $controller = new DepositController(
        app(\App\Services\EducationalDepositService::class),
        app(\App\Services\WalletAddressService::class)
    );
    
    // Call the remove method
    $response = $controller->removeMetaMaskAddress($request);
    $responseData = json_decode($response->getContent(), true);
    
    echo "   📡 API Response Status: {$response->getStatusCode()}\n";
    echo "   📋 Response Data:\n";
    print_r($responseData);
    
    if ($response->getStatusCode() === 200 && $responseData['success']) {
        echo "   ✅ Remove API test PASSED\n";
    } else {
        echo "   ❌ Remove API test FAILED\n";
    }
    
    echo "\n🎉 API test completed!\n";
    
} catch (Exception $e) {
    echo "❌ Test failed with error: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . "\n";
    echo "📍 Line: " . $e->getLine() . "\n";
}