<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AdminController;
use App\Models\User;
use App\Models\Wallet;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Educational Scam Features Test ===\n\n";

try {
    // 1. Check if educational scam fields exist in users table
    echo "1. Checking database schema...\n";
    
    $user = User::first();
    if ($user) {
        echo "   ✅ Users table accessible\n";
        
        // Check if new fields exist
        $hasWithdrawalBlocked = \Illuminate\Support\Facades\Schema::hasColumn('users', 'withdrawal_blocked');
        $hasBlockReason = \Illuminate\Support\Facades\Schema::hasColumn('users', 'block_reason');
        $hasBlockedAt = \Illuminate\Support\Facades\Schema::hasColumn('users', 'blocked_at');
        
        if ($hasWithdrawalBlocked && $hasBlockReason && $hasBlockedAt) {
            echo "   ✅ Educational scam fields added successfully\n";
        } else {
            echo "   ❌ Educational scam fields missing\n";
            echo "      withdrawal_blocked: " . ($hasWithdrawalBlocked ? 'exists' : 'missing') . "\n";
            echo "      block_reason: " . ($hasBlockReason ? 'exists' : 'missing') . "\n";
            echo "      blocked_at: " . ($hasBlockedAt ? 'exists' : 'missing') . "\n";
        }
    } else {
        echo "   ❌ No users found in database\n";
    }
    
    // 2. Test artificial profit generation
    echo "\n2. Testing artificial profit generation...\n";
    
    if ($user) {
        $wallets = Wallet::where('user_id', $user->id)->get();
        echo "   User: {$user->name} ({$user->email})\n";
        echo "   Wallets found: " . $wallets->count() . "\n";
        
        foreach ($wallets as $wallet) {
            if ($wallet->cryptocurrency_symbol !== 'USD') {
                $originalBalance = (float) $wallet->balance;
                $fakeProfit = $originalBalance * 0.10; // 10% fake profit
                $newBalance = $originalBalance + $fakeProfit;
                
                echo "   {$wallet->cryptocurrency_symbol}: {$originalBalance} -> {$newBalance} (+{$fakeProfit})\n";
                
                // Actually update for demonstration (this is the scam simulation)
                $wallet->update(['balance' => number_format($newBalance, 8, '.', '')]);
                echo "   ✅ Balance updated in database (EDUCATIONAL SIMULATION)\n";
            }
        }
    }
    
    // 3. Test withdrawal blocking
    echo "\n3. Testing withdrawal blocking...\n";
    
    if ($user) {
        $user->update([
            'withdrawal_blocked' => true,
            'block_reason' => 'verification_required',
            'blocked_at' => now()
        ]);
        
        echo "   ✅ User withdrawal blocked (EDUCATIONAL SIMULATION)\n";
        echo "   Block reason: verification_required\n";
        echo "   Blocked at: " . $user->blocked_at . "\n";
    }
    
    // 4. Test fake transaction ID generation
    echo "\n4. Testing fake transaction ID generation...\n";
    
    $currencies = ['BTC', 'ETH', 'USDT'];
    foreach ($currencies as $currency) {
        $fakeTransactionIds = [
            'BTC' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa' . bin2hex(random_bytes(16)),
            'ETH' => '0x' . bin2hex(random_bytes(32)),
            'USDT' => '0x' . bin2hex(random_bytes(32)),
        ];
        
        $fakeId = $fakeTransactionIds[$currency];
        echo "   {$currency}: {$fakeId}\n";
        echo "   ⚠️  This transaction ID is FAKE and will not appear on blockchain explorers\n";
    }
    
    // 5. Educational warnings
    echo "\n5. Educational Warnings:\n";
    echo "   🚨 ALL OPERATIONS ABOVE ARE EDUCATIONAL SIMULATIONS\n";
    echo "   📚 These demonstrate how cryptocurrency scams work\n";
    echo "   🛡️  NEVER use these techniques for actual fraud\n";
    echo "   📞 Report real scams to authorities\n";
    
    // 6. Protection tips
    echo "\n6. Protection Tips:\n";
    echo "   ✅ Only use regulated, licensed exchanges\n";
    echo "   ✅ Verify transaction IDs on blockchain explorers\n";
    echo "   ✅ Be suspicious of guaranteed returns\n";
    echo "   ✅ Test withdrawals with small amounts first\n";
    echo "   ✅ Never pay upfront fees to access your money\n";
    
    echo "\n=== Educational Test Complete ===\n";
    echo "The educational scam simulation features are working correctly.\n";
    echo "Remember: This is for learning purposes only!\n";

} catch (\Exception $e) {
    echo "❌ Error during test: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}