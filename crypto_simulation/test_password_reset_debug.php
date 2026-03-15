<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Http\Request;
use App\Services\PasswordResetService;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Password Reset API Debug Test ===\n\n";

// Test email for password reset
$testEmail = 'test@example.com';

try {
    // 1. Test PasswordResetService directly
    echo "1. Testing PasswordResetService directly...\n";
    $passwordResetService = new PasswordResetService();
    
    $result = $passwordResetService->sendResetLink($testEmail);
    echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";
    
    // 2. Check if password_reset_tokens table exists and has data
    echo "2. Checking password_reset_tokens table...\n";
    try {
        $tokens = DB::table('password_reset_tokens')->get();
        echo "Found " . count($tokens) . " tokens in database\n";
        foreach ($tokens as $token) {
            echo "- Email: {$token->email}, Created: {$token->created_at}\n";
        }
    } catch (Exception $e) {
        echo "Error accessing password_reset_tokens table: " . $e->getMessage() . "\n";
    }
    echo "\n";
    
    // 3. Test API endpoint directly
    echo "3. Testing API endpoint via cURL...\n";
    
    $url = 'http://127.0.0.1:8000/api/auth/password/reset/request';
    $data = json_encode(['email' => $testEmail]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Requested-With: XMLHttpRequest'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "cURL Error: $error\n";
    } else {
        echo "HTTP Code: $httpCode\n";
        echo "Response: $response\n";
        
        $responseData = json_decode($response, true);
        if ($responseData) {
            echo "Parsed Response: " . json_encode($responseData, JSON_PRETTY_PRINT) . "\n";
        }
    }
    echo "\n";
    
    // 4. Test with different email formats
    echo "4. Testing with different email formats...\n";
    
    $testEmails = [
        'user@example.com',
        'test.user@domain.co.uk',
        'invalid-email',
        ''
    ];
    
    foreach ($testEmails as $email) {
        echo "Testing email: '$email'\n";
        try {
            $result = $passwordResetService->sendResetLink($email);
            echo "  Result: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
            echo "  Message: " . $result['message'] . "\n";
        } catch (Exception $e) {
            echo "  Exception: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
    
    // 5. Check Laravel logs for any errors
    echo "5. Checking recent Laravel logs...\n";
    $logFile = storage_path('logs/laravel.log');
    if (file_exists($logFile)) {
        $logs = file_get_contents($logFile);
        $recentLogs = substr($logs, -2000); // Last 2000 characters
        echo "Recent log entries:\n";
        echo $recentLogs . "\n";
    } else {
        echo "No log file found at: $logFile\n";
    }
    
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Debug Test Complete ===\n";