<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "Resetting admin password...\n\n";

try {
    $admin = User::where('email', 'admin@cryptoexchange.com')->first();
    
    if ($admin) {
        $admin->password = bcrypt('password123');
        $admin->save();
        
        echo "Admin password reset successfully!\n";
        echo "Email: {$admin->email}\n";
        echo "Password: password123\n";
    } else {
        echo "Admin user not found. Creating new admin...\n";
        
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@cryptoexchange.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
            'is_admin' => true
        ]);
        
        echo "Admin user created!\n";
        echo "Email: {$admin->email}\n";
        echo "Password: password123\n";
    }
    
    // Also create a regular test user for frontend testing
    $testUser = User::where('email', 'test@example.com')->first();
    if (!$testUser) {
        $testUser = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
            'is_admin' => false
        ]);
        echo "Test user created: {$testUser->email}\n";
    } else {
        $testUser->password = bcrypt('password123');
        $testUser->save();
        echo "Test user password reset: {$testUser->email}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nDone.\n";