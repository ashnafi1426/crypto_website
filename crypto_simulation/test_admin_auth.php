<?php

echo "=== Testing Admin Authentication ===\n\n";

// Test admin login
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
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "1. Admin Login Test:\n";
echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

if ($httpCode === 200) {
    $loginResult = json_decode($response, true);
    if (isset($loginResult['token'])) {
        $token = $loginResult['token'];
        echo "✅ Admin login successful!\n";
        echo "Token: " . substr($token, 0, 20) . "...\n\n";
        
        // Test admin dashboard access
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/admin/dashboard');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $dashboardResponse = curl_exec($ch);
        $dashboardHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "2. Admin Dashboard Access Test:\n";
        echo "HTTP Code: $dashboardHttpCode\n";
        
        if ($dashboardHttpCode === 200) {
            echo "✅ Admin dashboard access successful!\n";
            $dashboardData = json_decode($dashboardResponse, true);
            echo "Dashboard data keys: " . implode(', ', array_keys($dashboardData)) . "\n\n";
        } else {
            echo "❌ Admin dashboard access failed!\n";
            echo "Response: $dashboardResponse\n\n";
        }
        
        // Test regular user access (should fail)
        echo "3. Testing Regular User Access to Admin Routes:\n";
        
        // Login as regular user
        $regularLoginData = [
            'email' => 'john@example.com',
            'password' => 'password123'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/auth/login');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($regularLoginData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $regularResponse = curl_exec($ch);
        $regularHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($regularHttpCode === 200) {
            $regularResult = json_decode($regularResponse, true);
            $regularToken = $regularResult['token'];
            
            // Try to access admin dashboard with regular user token
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/admin/dashboard');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $regularToken,
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $unauthorizedResponse = curl_exec($ch);
            $unauthorizedHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            echo "Regular user trying to access admin dashboard:\n";
            echo "HTTP Code: $unauthorizedHttpCode\n";
            
            if ($unauthorizedHttpCode === 403) {
                echo "✅ Access properly denied for regular user!\n";
            } else {
                echo "❌ Security issue: Regular user can access admin routes!\n";
                echo "Response: $unauthorizedResponse\n";
            }
        }
        
    } else {
        echo "❌ Login failed - no token received\n";
    }
} else {
    echo "❌ Admin login failed!\n";
}

echo "\n=== Summary ===\n";
echo "Admin Credentials:\n";
echo "Email: admin@cryptoexchange.com\n";
echo "Password: admin123\n";
echo "Server: http://127.0.0.1:8000\n";
echo "Admin Dashboard: http://127.0.0.1:8000/api/admin/dashboard\n";