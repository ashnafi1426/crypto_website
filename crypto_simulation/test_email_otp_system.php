<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Test Email and OTP System
class EmailOtpSystemTest
{
    private $baseUrl = 'http://localhost:8000/api';
    private $testEmail = 'ashenafiashew074@gmail.com';
    private $testPassword = 'password123';
    private $token = null;

    public function runTests()
    {
        echo "🧪 Testing Email Verification and OTP System\n";
        echo "=" . str_repeat("=", 50) . "\n\n";

        try {
            // Test 1: Register a test user
            $this->testUserRegistration();
            
            // Test 2: Login to get token
            $this->testUserLogin();
            
            // Test 3: Check email verification status
            $this->testEmailVerificationStatus();
            
            // Test 4: Send email verification
            $this->testSendEmailVerification();
            
            // Test 5: Generate OTP for email verification
            $this->testGenerateOtp();
            
            // Test 6: Get OTP status
            $this->testGetOtpStatus();
            
            // Test 7: Test email configuration
            $this->testEmailConfiguration();
            
            echo "\n✅ All tests completed!\n";
            
        } catch (Exception $e) {
            echo "❌ Test failed: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
    }

    private function testUserRegistration()
    {
        echo "1️⃣ Testing User Registration...\n";
        
        $data = [
            'name' => 'Test User',
            'email' => $this->testEmail,
            'password' => $this->testPassword,
            'password_confirmation' => $this->testPassword
        ];

        $response = $this->makeRequest('POST', '/auth/register', $data);
        
        if ($response['success']) {
            echo "   ✅ User registered successfully\n";
            $this->token = $response['data']['token'] ?? null;
        } else {
            echo "   ℹ️ User might already exist: " . ($response['message'] ?? 'Unknown error') . "\n";
        }
        echo "\n";
    }

    private function testUserLogin()
    {
        echo "2️⃣ Testing User Login...\n";
        
        $data = [
            'email' => $this->testEmail,
            'password' => $this->testPassword
        ];

        $response = $this->makeRequest('POST', '/auth/login', $data);
        
        if ($response['success']) {
            echo "   ✅ Login successful\n";
            $this->token = $response['data']['token'];
            echo "   📝 Token: " . substr($this->token, 0, 20) . "...\n";
        } else {
            throw new Exception("Login failed: " . ($response['message'] ?? 'Unknown error'));
        }
        echo "\n";
    }

    private function testEmailVerificationStatus()
    {
        echo "3️⃣ Testing Email Verification Status...\n";
        
        $response = $this->makeRequest('GET', '/auth/email-verification-status', [], $this->token);
        
        if ($response['success']) {
            echo "   ✅ Email verification status retrieved\n";
            echo "   📧 Is verified: " . ($response['data']['is_verified'] ? 'Yes' : 'No') . "\n";
            echo "   🔄 Can resend: " . ($response['data']['can_resend'] ? 'Yes' : 'No') . "\n";
            echo "   📊 Attempts remaining: " . ($response['data']['attempts_remaining'] ?? 'N/A') . "\n";
        } else {
            echo "   ❌ Failed to get verification status: " . ($response['message'] ?? 'Unknown error') . "\n";
        }
        echo "\n";
    }

    private function testSendEmailVerification()
    {
        echo "4️⃣ Testing Send Email Verification...\n";
        
        $response = $this->makeRequest('POST', '/auth/send-email-verification', [], $this->token);
        
        if ($response['success']) {
            echo "   ✅ Email verification sent successfully\n";
            echo "   📧 Message: " . ($response['message'] ?? 'No message') . "\n";
        } else {
            echo "   ⚠️ Email verification send result: " . ($response['message'] ?? 'Unknown error') . "\n";
        }
        echo "\n";
    }

    private function testGenerateOtp()
    {
        echo "5️⃣ Testing Generate OTP...\n";
        
        $data = [
            'identifier' => $this->testEmail,
            'type' => 'email',
            'purpose' => 'email_verification'
        ];

        $response = $this->makeRequest('POST', '/auth/generate-otp', $data, $this->token);
        
        if ($response['success']) {
            echo "   ✅ OTP generated successfully\n";
            echo "   📧 Message: " . ($response['message'] ?? 'No message') . "\n";
            
            // In development, OTP might be returned
            if (isset($response['otp_code'])) {
                echo "   🔐 Development OTP: " . $response['otp_code'] . "\n";
            }
        } else {
            echo "   ⚠️ OTP generation result: " . ($response['message'] ?? 'Unknown error') . "\n";
        }
        echo "\n";
    }

    private function testGetOtpStatus()
    {
        echo "6️⃣ Testing Get OTP Status...\n";
        
        $params = [
            'identifier' => $this->testEmail,
            'type' => 'email',
            'purpose' => 'email_verification'
        ];

        $response = $this->makeRequest('GET', '/auth/otp-status?' . http_build_query($params), [], $this->token);
        
        if ($response['success']) {
            echo "   ✅ OTP status retrieved\n";
            echo "   🔐 Has active OTP: " . ($response['data']['has_active_otp'] ? 'Yes' : 'No') . "\n";
            echo "   🔄 Can resend: " . ($response['data']['can_resend'] ? 'Yes' : 'No') . "\n";
            echo "   📊 Attempts remaining: " . ($response['data']['attempts_remaining'] ?? 'N/A') . "\n";
            
            if (isset($response['data']['expires_at'])) {
                echo "   ⏰ Expires at: " . $response['data']['expires_at'] . "\n";
            }
        } else {
            echo "   ❌ Failed to get OTP status: " . ($response['message'] ?? 'Unknown error') . "\n";
        }
        echo "\n";
    }

    private function testEmailConfiguration()
    {
        echo "7️⃣ Testing Email Configuration...\n";
        
        // Check Laravel configuration
        $mailDriver = config('mail.default');
        $mailHost = config('mail.mailers.smtp.host');
        $mailPort = config('mail.mailers.smtp.port');
        $mailUsername = config('mail.mailers.smtp.username');
        $mailFromAddress = config('mail.from.address');
        
        echo "   📧 Mail Driver: " . $mailDriver . "\n";
        echo "   🌐 SMTP Host: " . $mailHost . "\n";
        echo "   🔌 SMTP Port: " . $mailPort . "\n";
        echo "   👤 SMTP Username: " . $mailUsername . "\n";
        echo "   📮 From Address: " . $mailFromAddress . "\n";
        
        // Test if mail configuration is valid
        if ($mailDriver === 'smtp' && $mailHost && $mailUsername) {
            echo "   ✅ Email configuration looks valid\n";
        } else {
            echo "   ⚠️ Email configuration might need attention\n";
        }
        echo "\n";
    }

    private function makeRequest($method, $endpoint, $data = [], $token = null)
    {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL Error: " . $error);
        }
        
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode >= 400) {
            echo "   ⚠️ HTTP $httpCode: " . ($decodedResponse['message'] ?? $response) . "\n";
        }
        
        return $decodedResponse ?: ['success' => false, 'message' => 'Invalid response'];
    }
}

// Run the tests
$tester = new EmailOtpSystemTest();
$tester->runTests();