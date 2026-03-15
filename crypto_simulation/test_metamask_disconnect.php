<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\DepositAddress;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\DepositController;

// Initialize Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Testing MetaMask Disconnect Endpoint\n";
echo "=====================================\n\n";

try {
    // 1. Find or create a test user
    $testUser = User::where('email', 'test@metamask.com')->first();
    
    if (!$testUser) {
        $testUser = User::create([
            'name' => 'MetaMask Test User',
            'email' => 'test@metamask.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now()
        ]);
        echo "✅ Test user created: {$testUser->email}\n";
    } else {
        echo "✅ Test user found: {$testUser->email}\n";
    }

    // 2. Create a MetaMask deposit address
    $depositAddress = DepositAddress::create([
        'user_id' => $testUser->id,
        'currency' => 'ETH',
        'network' => 'Ethereum',
        'address' => '0x742d35Cc6634C0532925a3b8D4C2C4e07C8B8C8B',
        'type' => 'metamask',
        'is_active' => true
    ]);
    echo "✅ MetaMask address created: {$depositAddress->address}\n";

    // 3. Test the disconnect endpoint with proper request data
    echo "\n🧪 Testing disconnect endpoint...\n";
    
    // Create a mock request with the required data
    $requestData = [
        'currency' => 'ETH',
        'network' => 'Ethereum'
    ];
    
    // Create request instance
    $request = new Request();
    $request->merge($requestData);
    
    // Mock the authenticated user
    $request->setUserResolver(function () use ($testUser) {
        return $testUser;
    });
    
    // Initialize controller
    $controller = new DepositController(
        app(\App\Services\EducationalDepositService::class),
        app(\App\Services\WalletAddressService::class)
    );
    
    // Call the disconnect method
    $response = $controller->removeMetaMaskAddress($request);
    $responseData = json_decode($response->getContent(), true);
    
    echo "Response Status: {$response->getStatusCode()}\n";
    echo "Response Data: " . json_encode($responseData, JSON_PRETTY_PRINT) . "\n";
    
    if ($response->getStatusCode() === 200 && $responseData['success']) {
        echo "✅ Disconnect endpoint working correctly!\n";
        
        // Verify the address was deactivated
        $updatedAddress = DepositAddress::find($depositAddress->id);
        if (!$updatedAddress->is_active) {
            echo "✅ Address successfully deactivated\n";
        } else {
            echo "❌ Address was not deactivated\n";
        }
    } else {
        echo "❌ Disconnect endpoint failed\n";
        if (isset($responseData['errors'])) {
            echo "Validation errors: " . json_encode($responseData['errors'], JSON_PRETTY_PRINT) . "\n";
        }
    }

    // 4. Test with missing parameters
    echo "\n🧪 Testing with missing parameters...\n";
    
    $emptyRequest = new Request();
    $emptyRequest->setUserResolver(function () use ($testUser) {
        return $testUser;
    });
    
    $emptyResponse = $controller->removeMetaMaskAddress($emptyRequest);
    $emptyResponseData = json_decode($emptyResponse->getContent(), true);
    
    echo "Empty Request Status: {$emptyResponse->getStatusCode()}\n";
    echo "Empty Request Response: " . json_encode($emptyResponseData, JSON_PRETTY_PRINT) . "\n";
    
    if ($emptyResponse->getStatusCode() === 422) {
        echo "✅ Validation working correctly for missing parameters\n";
    } else {
        echo "❌ Validation not working as expected\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n📋 SUMMARY:\n";
echo "- The endpoint expects 'currency' and 'network' parameters\n";
echo "- Both parameters are required (validation will fail if missing)\n";
echo "- The endpoint deactivates the MetaMask address instead of deleting it\n";
echo "- Frontend should send: {\"currency\": \"ETH\", \"network\": \"Ethereum\"}\n";