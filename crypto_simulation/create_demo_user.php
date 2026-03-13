<?php

// Create demo user script
require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

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
    // Check if demo user exists
    $existingUser = Capsule::table('users')->where('email', 'demo@crypto.com')->first();
    
    if ($existingUser) {
        echo "Demo user already exists with ID: " . $existingUser->id . "\n";
        echo "Email: " . $existingUser->email . "\n";
    } else {
        // Create demo user
        $userId = Capsule::table('users')->insertGetId([
            'name' => 'Demo User',
            'email' => 'demo@crypto.com',
            'password' => password_hash('demo123', PASSWORD_DEFAULT),
            'email_verified_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        echo "Demo user created successfully!\n";
        echo "ID: " . $userId . "\n";
        echo "Email: demo@crypto.com\n";
        echo "Password: demo123\n";
        
        // Create wallets for the demo user
        $cryptocurrencies = [
            ['symbol' => 'USD', 'balance' => '50000.00000000'],
            ['symbol' => 'BTC', 'balance' => '2.50000000'],
            ['symbol' => 'ETH', 'balance' => '15.75000000'],
            ['symbol' => 'SOL', 'balance' => '100.00000000'],
            ['symbol' => 'BNB', 'balance' => '25.00000000'],
            ['symbol' => 'XRP', 'balance' => '5000.00000000'],
            ['symbol' => 'AVAX', 'balance' => '200.00000000']
        ];
        
        foreach ($cryptocurrencies as $crypto) {
            Capsule::table('wallets')->insert([
                'user_id' => $userId,
                'cryptocurrency_symbol' => $crypto['symbol'],
                'balance' => $crypto['balance'],
                'reserved_balance' => '0.00000000',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        echo "Demo wallets created with realistic balances\n";
        
        // Create some sample orders
        $sampleOrders = [
            [
                'user_id' => $userId,
                'cryptocurrency_symbol' => 'BTC',
                'order_type' => 'limit',
                'side' => 'buy',
                'quantity' => '0.50000000',
                'price' => '67500.00000000',
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'user_id' => $userId,
                'cryptocurrency_symbol' => 'ETH',
                'order_type' => 'limit',
                'side' => 'sell',
                'quantity' => '2.00000000',
                'price' => '3600.00000000',
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ];
        
        foreach ($sampleOrders as $order) {
            Capsule::table('orders')->insert($order);
        }
        
        echo "Sample orders created\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}