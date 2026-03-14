<?php

require_once 'vendor/autoload.php';

use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminController;

// Test admin authentication and dashboard
echo "=== Testing Admin Dashboard ===\n\n";

try {
    // Test admin login
    echo "1. Testing admin login...\n";
    $authController = new AuthController();
    
    $loginRequest = new Request([
        'email' => 'admin@cryptoexchange.com',
        'password' => 'admin123'
    ]);
    
    $loginResponse = $authController->login($loginRequest);
    $loginData = json_decode($loginResponse->getContent(), true);
    
    if ($loginData['success']) {
        echo "✓ Admin login successful\n";
        echo "  Token: " . substr($loginData['token'], 0, 20) . "...\n";
        echo "  User: " . $loginData['user']['name'] . " (Admin: " . ($loginData['user']['is_admin'] ? 'Yes' : 'No') . ")\n\n";
        
        $token = $loginData['token'];
        
        // Test dashboard endpoint
        echo "2. Testing dashboard endpoint...\n";
        $adminController = app(AdminController::class);
        
        // Create authenticated request
        $dashboardRequest = new Request();
        $dashboardRequest->headers->set('Authorization', 'Bearer ' . $token);
        
        // Set the authenticated user
        $user = \App\Models\User::where('email', 'admin@cryptoexchange.com')->first();
        $dashboardRequest->setUserResolver(function () use ($user) {
            return $user;
        });
        
        $dashboardResponse = $adminController->dashboard();
        $dashboardData = json_decode($dashboardResponse->getContent(), true);
        
        if ($dashboardData['success']) {
            echo "✓ Dashboard data retrieved successfully\n";
            echo "  Total Users: " . $dashboardData['data']['stats']['total_users'] . "\n";
            echo "  Total Deposits: $" . number_format($dashboardData['data']['stats']['total_deposits'], 2) . "\n";
            echo "  Recent Transactions: " . count($dashboardData['data']['recent_transactions']) . "\n\n";
        } else {
            echo "✗ Dashboard failed: " . $dashboardData['message'] . "\n\n";
        }
        
        // Test analytics endpoint
        echo "3. Testing analytics endpoint...\n";
        $analyticsResponse = $adminController->getAnalytics();
        $analyticsData = json_decode($analyticsResponse->getContent(), true);
        
        if ($analyticsData['success']) {
            echo "✓ Analytics data retrieved successfully\n";
            if (isset($analyticsData['note'])) {
                echo "  Note: " . $analyticsData['note'] . "\n";
            }
            echo "  Overview data available: " . (isset($analyticsData['data']['overview']) ? 'Yes' : 'No') . "\n\n";
        } else {
            echo "✗ Analytics failed: " . $analyticsData['message'] . "\n\n";
        }
        
        // Test users endpoint
        echo "4. Testing users endpoint...\n";
        $usersResponse = $adminController->users($dashboardRequest);
        $usersData = json_decode($usersResponse->getContent(), true);
        
        if ($usersData['success']) {
            echo "✓ Users data retrieved successfully\n";
            echo "  Total Users: " . count($usersData['users']) . "\n";
            echo "  First User: " . ($usersData['users'][0]['name'] ?? 'N/A') . "\n\n";
        } else {
            echo "✗ Users failed: " . $usersData['message'] . "\n\n";
        }
        
    } else {
        echo "✗ Admin login failed: " . $loginData['message'] . "\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "  File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== Test Complete ===\n";