<?php

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
    // Check if user exists
    $user = Capsule::table('users')->where('email', 'test@example.com')->first();
    
    if ($user) {
        echo "✅ User exists: ID {$user->id}, Email: {$user->email}\n";
        
        // Check wallets
        $wallets = Capsule::table('wallets')->where('user_id', $user->id)->get();
        echo "💰 User has {$wallets->count()} wallets\n";
        
        if ($wallets->count() == 0) {
            echo "Creating wallets...\n";
            
            // Get available cryptocurrencies
            $cryptos = Capsule::table('cryptocurrencies')->get();
            
            foreach ($cryptos as $crypto) {
                $balance = $crypto->symbol === 'USD' ? '10000.00000000' : '0.00000000';
                
                Capsule::table('wallets')->insert([
                    'user_id' => $user->id,
                    'cryptocurrency_symbol' => $crypto->symbol,
                    'balance' => $balance,
                    'reserved_balance' => '0.00000000',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
                echo "  - Created {$crypto->symbol} wallet with balance: {$balance}\n";
            }
        } else {
            echo "Existing wallets:\n";
            foreach ($wallets as $wallet) {
                echo "  - {$wallet->cryptocurrency_symbol}: {$wallet->balance}\n";
            }
        }
        
    } else {
        echo "❌ User not found. Creating user...\n";
        
        $userId = Capsule::table('users')->insertGetId([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => password_hash('TestPassword123!', PASSWORD_DEFAULT),
            'email_verified_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        echo "✅ User created with ID: {$userId}\n";
        
        // Create wallets
        $cryptos = Capsule::table('cryptocurrencies')->get();
        
        foreach ($cryptos as $crypto) {
            $balance = $crypto->symbol === 'USD' ? '10000.00000000' : '0.00000000';
            
            Capsule::table('wallets')->insert([
                'user_id' => $userId,
                'cryptocurrency_symbol' => $crypto->symbol,
                'balance' => $balance,
                'reserved_balance' => '0.00000000',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            echo "  - Created {$crypto->symbol} wallet with balance: {$balance}\n";
        }
    }
    
    echo "\n🎯 Ready to test! Use these credentials:\n";
    echo "Email: test@example.com\n";
    echo "Password: TestPassword123!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}