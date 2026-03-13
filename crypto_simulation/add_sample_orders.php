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
    // Get user ID
    $user = Capsule::table('users')->where('email', 'ashnafisileshi72@gmail.com')->first();
    
    if (!$user) {
        echo "❌ User not found\n";
        exit(1);
    }
    
    echo "Adding sample orders for user {$user->id}...\n";
    
    // Sample BTC orders
    $sampleOrders = [
        // Buy orders (bids)
        ['BTC', 'limit', 'buy', '0.001', '43000.00'],
        ['BTC', 'limit', 'buy', '0.002', '42500.00'],
        ['BTC', 'limit', 'buy', '0.005', '42000.00'],
        
        // Sell orders (asks) - but user needs BTC first
        // Let's give user some BTC
    ];
    
    // First, give user some BTC to create sell orders
    Capsule::table('wallets')
        ->where('user_id', $user->id)
        ->where('cryptocurrency_symbol', 'BTC')
        ->update(['balance' => '0.1']);
    
    echo "✅ Added 0.1 BTC to user wallet\n";
    
    // Add sell orders
    $sampleOrders = array_merge($sampleOrders, [
        ['BTC', 'limit', 'sell', '0.001', '45500.00'],
        ['BTC', 'limit', 'sell', '0.002', '46000.00'],
        ['BTC', 'limit', 'sell', '0.003', '46500.00'],
    ]);
    
    foreach ($sampleOrders as $order) {
        [$symbol, $type, $side, $quantity, $price] = $order;
        
        $orderId = Capsule::table('orders')->insertGetId([
            'user_id' => $user->id,
            'cryptocurrency_symbol' => $symbol,
            'order_type' => $type,
            'side' => $side,
            'quantity' => $quantity,
            'price' => $price,
            'filled_quantity' => '0.00000000',
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        echo "  - Created {$side} order: {$quantity} {$symbol} @ \${$price} (ID: {$orderId})\n";
    }
    
    echo "\n🎯 Sample orders created successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}