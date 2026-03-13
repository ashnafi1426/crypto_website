<?php

// Simple user creation script
require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\Hash;

// Setup database connection
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => __DIR__ . '/database/database.sqlite',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

try {
    // Check if user exists
    $existingUser = Capsule::table('users')->where('email', 'test@example.com')->first();
    
    if ($existingUser) {
        echo "User already exists with ID: " . $existingUser->id . "\n";
        echo "Email: " . $existingUser->email . "\n";
    } else {
        // Create new user
        $userId = Capsule::table('users')->insertGetId([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => password_hash('TestPassword123!', PASSWORD_DEFAULT),
            'email_verified_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        echo "User created successfully!\n";
        echo "ID: " . $userId . "\n";
        echo "Email: test@example.com\n";
        echo "Password: TestPassword123!\n";
        
        // Create wallets for the user
        $cryptocurrencies = ['BTC', 'ETH', 'SOL', 'BNB', 'XRP', 'AVAX', 'USD'];
        
        foreach ($cryptocurrencies as $symbol) {
            $balance = $symbol === 'USD' ? '10000.00000000' : '0.00000000';
            
            Capsule::table('wallets')->insert([
                'user_id' => $userId,
                'cryptocurrency_symbol' => $symbol,
                'balance' => $balance,
                'reserved_balance' => '0.00000000',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        echo "Wallets created with $10,000 USD starting balance\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}