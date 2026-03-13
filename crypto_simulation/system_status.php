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

echo "🚀 Crypto Exchange System Status Check\n";
echo "=====================================\n\n";

try {
    // Check database tables
    echo "📊 Database Status:\n";
    $tables = ['users', 'cryptocurrencies', 'wallets', 'orders', 'trades', 'price_history', 'transaction_records'];
    
    foreach ($tables as $table) {
        $count = Capsule::table($table)->count();
        echo "  ✅ {$table}: {$count} records\n";
    }
    
    echo "\n👤 User Account Status:\n";
    $user = Capsule::table('users')->where('email', 'ashnafisileshi72@gmail.com')->first();
    if ($user) {
        echo "  ✅ User exists: ID {$user->id}, Email: {$user->email}\n";
        
        // Check wallets
        $wallets = Capsule::table('wallets')->where('user_id', $user->id)->get();
        echo "  💰 Wallets ({$wallets->count()}):\n";
        foreach ($wallets as $wallet) {
            echo "    - {$wallet->cryptocurrency_symbol}: {$wallet->balance}\n";
        }
        
        // Check orders
        $orders = Capsule::table('orders')->where('user_id', $user->id)->count();
        echo "  📋 Orders: {$orders}\n";
        
    } else {
        echo "  ❌ User not found\n";
    }
    
    echo "\n💱 Market Data Status:\n";
    $cryptos = Capsule::table('cryptocurrencies')->get();
    foreach ($cryptos as $crypto) {
        echo "  📈 {$crypto->symbol} ({$crypto->name}): \${$crypto->current_price}\n";
    }
    
    echo "\n📊 Order Book Status:\n";
    $pendingOrders = Capsule::table('orders')->where('status', 'pending')->get();
    $buyOrders = $pendingOrders->where('side', 'buy')->count();
    $sellOrders = $pendingOrders->where('side', 'sell')->count();
    echo "  📈 Buy Orders: {$buyOrders}\n";
    echo "  📉 Sell Orders: {$sellOrders}\n";
    
    echo "\n🔧 Cache Status:\n";
    $cacheEntries = Capsule::table('cache')->count();
    echo "  💾 Cache entries: {$cacheEntries}\n";
    
    echo "\n✅ System Status: OPERATIONAL\n";
    echo "🌐 Backend: http://127.0.0.1:8000\n";
    echo "🖥️  Frontend: http://localhost:5173\n";
    echo "📋 Demo: http://localhost:5173/demo-features.html\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}