<?php

/**
 * Comprehensive Admin Backend Test Script
 * 
 * This script tests all admin endpoints to ensure they work correctly.
 * Run this after seeding the database with admin test data.
 */

require_once __DIR__ . '/vendor/autoload.php';

class AdminBackendTester
{
    private $baseUrl;
    private $adminToken;
    private $testResults = [];

    public function __construct($baseUrl = 'http://127.0.0.1:8000/api')
    {
        $this->baseUrl = $baseUrl;
    }

    public function runAllTests()
    {
        echo "🚀 Starting Admin Backend Tests...\n\n";

        // Step 1: Login as admin
        if (!$this->loginAsAdmin()) {
            echo "❌ Failed to login as admin. Cannot continue tests.\n";
            return;
        }

        // Step 2: Test all admin endpoints
        $this->testDashboardEndpoints();
        $this->testUserManagementEndpoints();
        $this->testKycManagementEndpoints();
        $this->testSupportTicketEndpoints();
        $this->testReferralManagementEndpoints();
        $this->testInvestmentManagementEndpoints();
        $this->testWalletManagementEndpoints();
        $this->testTransactionManagementEndpoints();
        $this->testSystemControlEndpoints();

        // Step 3: Display results
        $this->displayResults();
    }

    private function loginAsAdmin()
    {
        echo "🔐 Logging in as admin...\n";
        
        $response = $this->makeRequest('POST', '/auth/login', [
            'email' => 'admin@cryptoexchange.com',
            'password' => 'admin123'
        ]);

        if ($response && isset($response['success']) && $response['success']) {
            $this->adminToken = $response['token'];
            echo "✅ Admin login successful\n\n";
            return true;
        }

        echo "❌ Admin login failed\n";
        return false;
    }

    private function testDashboardEndpoints()
    {
        echo "📊 Testing Dashboard Endpoints...\n";
        
        $endpoints = [
            'GET /admin/dashboard' => '/admin/dashboard',
            'GET /admin/analytics' => '/admin/analytics',
            'GET /admin/real-time-metrics' => '/admin/real-time-metrics',
            'GET /admin/system-metrics' => '/admin/system-metrics',
        ];

        foreach ($endpoints as $name => $endpoint) {
            $this->testEndpoint($name, 'GET', $endpoint);
        }
        echo "\n";
    }

    private function testUserManagementEndpoints()
    {
        echo "👥 Testing User Management Endpoints...\n";
        
        $endpoints = [
            'GET /admin/users' => '/admin/users',
            'GET /admin/users/1' => '/admin/users/1',
            'GET /admin/suspicious-activities' => '/admin/suspicious-activities',
        ];

        foreach ($endpoints as $name => $endpoint) {
            $this->testEndpoint($name, 'GET', $endpoint);
        }
        echo "\n";
    }

    private function testKycManagementEndpoints()
    {
        echo "🆔 Testing KYC Management Endpoints...\n";
        
        $endpoints = [
            'GET /admin/kyc/submissions' => '/admin/kyc/submissions',
            'GET /admin/kyc/statistics' => '/admin/kyc/statistics',
        ];

        foreach ($endpoints as $name => $endpoint) {
            $this->testEndpoint($name, 'GET', $endpoint);
        }
        echo "\n";
    }

    private function testSupportTicketEndpoints()
    {
        echo "🎫 Testing Support Ticket Endpoints...\n";
        
        $endpoints = [
            'GET /admin/support/tickets' => '/admin/support/tickets',
            'GET /admin/support/statistics' => '/admin/support/statistics',
        ];

        foreach ($endpoints as $name => $endpoint) {
            $this->testEndpoint($name, 'GET', $endpoint);
        }
        echo "\n";
    }

    private function testReferralManagementEndpoints()
    {
        echo "🔗 Testing Referral Management Endpoints...\n";
        
        $endpoints = [
            'GET /admin/referrals/programs' => '/admin/referrals/programs',
            'GET /admin/referrals/statistics' => '/admin/referrals/statistics',
        ];

        foreach ($endpoints as $name => $endpoint) {
            $this->testEndpoint($name, 'GET', $endpoint);
        }
        echo "\n";
    }

    private function testInvestmentManagementEndpoints()
    {
        echo "💰 Testing Investment Management Endpoints...\n";
        
        $endpoints = [
            'GET /admin/investments' => '/admin/investments',
            'GET /admin/investments/statistics' => '/admin/investments/statistics',
        ];

        foreach ($endpoints as $name => $endpoint) {
            $this->testEndpoint($name, 'GET', $endpoint);
        }
        echo "\n";
    }

    private function testWalletManagementEndpoints()
    {
        echo "👛 Testing Wallet Management Endpoints...\n";
        
        $endpoints = [
            'GET /admin/wallets' => '/admin/wallets',
            'GET /admin/wallets/statistics' => '/admin/wallets/statistics',
        ];

        foreach ($endpoints as $name => $endpoint) {
            $this->testEndpoint($name, 'GET', $endpoint);
        }
        echo "\n";
    }

    private function testTransactionManagementEndpoints()
    {
        echo "💳 Testing Transaction Management Endpoints...\n";
        
        $endpoints = [
            'GET /admin/transactions/deposits' => '/admin/transactions/deposits',
            'GET /admin/transactions/withdrawals' => '/admin/transactions/withdrawals',
        ];

        foreach ($endpoints as $name => $endpoint) {
            $this->testEndpoint($name, 'GET', $endpoint);
        }
        echo "\n";
    }

    private function testSystemControlEndpoints()
    {
        echo "⚙️ Testing System Control Endpoints...\n";
        
        // Test maintenance mode toggle
        $this->testEndpoint('POST /admin/maintenance-mode', 'POST', '/admin/maintenance-mode', [
            'enabled' => false,
            'message' => 'Test maintenance mode'
        ]);
        echo "\n";
    }

    private function testEndpoint($name, $method, $endpoint, $data = null)
    {
        $response = $this->makeRequest($method, $endpoint, $data);
        
        if ($response && isset($response['success']) && $response['success']) {
            echo "✅ $name - PASSED\n";
            $this->testResults[$name] = 'PASSED';
        } else {
            echo "❌ $name - FAILED\n";
            $this->testResults[$name] = 'FAILED';
            if ($response && isset($response['message'])) {
                echo "   Error: " . $response['message'] . "\n";
            }
        }
    }

    private function makeRequest($method, $endpoint, $data = null)
    {
        $url = $this->baseUrl . $endpoint;
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        if ($this->adminToken) {
            $headers[] = 'Authorization: Bearer ' . $this->adminToken;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return null;
        }

        return json_decode($response, true);
    }

    private function displayResults()
    {
        echo "📋 Test Results Summary:\n";
        echo str_repeat("=", 50) . "\n";
        
        $passed = 0;
        $failed = 0;
        
        foreach ($this->testResults as $test => $result) {
            $status = $result === 'PASSED' ? '✅' : '❌';
            echo "$status $test\n";
            
            if ($result === 'PASSED') {
                $passed++;
            } else {
                $failed++;
            }
        }
        
        echo str_repeat("=", 50) . "\n";
        echo "Total Tests: " . count($this->testResults) . "\n";
        echo "Passed: $passed\n";
        echo "Failed: $failed\n";
        
        if ($failed === 0) {
            echo "\n🎉 All tests passed! Admin backend is working correctly.\n";
        } else {
            echo "\n⚠️  Some tests failed. Please check the Laravel logs for more details.\n";
        }
    }
}

// Run the tests
$tester = new AdminBackendTester();
$tester->runAllTests();