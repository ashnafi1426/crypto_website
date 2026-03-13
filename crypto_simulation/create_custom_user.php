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
    $email = 'ashnafisileshi72@gmail.com';
    $password = '14263208@aA';
    
    // Check if user already exists
    $existingUser = Capsule::table('users')->where('email', $email)->first();
    
    if ($existingUser) {
        echo "✅ User already exists: ID {$existingUser->id}, Email: {$existingUser->email}\n";
        $userId = $existingUser->id;
    } else {
        // Create new user
        $userId = Capsule::table('users')->insertGetId([
            'name' => 'Ashenafi Sileshi',
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'email_verified_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        echo "✅ User created successfully!\n";
        echo "ID: {$userId}\n";
        echo "Email: {$email}\n";
        echo "Name: Ashenafi Sileshi\n";
    }
    
    // Check and create wallets
    $wallets = Capsule::table('wallets')->where('user_id', $userId)->get();
    echo "💰 User has {$wallets->count()} wallets\n";
    
    if ($wallets->count() == 0) {
        echo "Creating wallets...\n";
        
        // Get available cryptocurrencies
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
    } else {
        echo "Existing wallets:\n";
        foreach ($wallets as $wallet) {
            echo "  - {$wallet->cryptocurrency_symbol}: {$wallet->balance}\n";
        }
    }
    
    echo "\n🎯 Ready to test! Use these credentials:\n";
    echo "Email: {$email}\n";
    echo "Password: {$password}\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}