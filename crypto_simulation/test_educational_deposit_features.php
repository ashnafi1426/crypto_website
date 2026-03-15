<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use App\Services\EducationalDepositService;
use App\Models\User;
use App\Models\Deposit;
use App\Models\Wallet;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Educational Deposit System Test ===\n\n";

try {
    // Get the educational deposit service
    $depositService = app(EducationalDepositService::class);
    
    // 1. Test deposit address generation
    echo "1. Testing deposit address generation...\n";
    
    $user = User::first();
    if (!$user) {
        echo "   ❌ No users found in database\n";
        exit(1);
    }
    
    echo "   Testing with user: {$user->name} ({$user->email})\n";
    
    $currencies = ['BTC', 'ETH', 'USDT', 'LTC', 'ADA', 'DOT'];
    foreach ($currencies as $currency) {
        $result = $depositService->generateRealDepositAddress($user, $currency);
        
        if ($result['success']) {
            echo "   ✅ {$currency}: {$result['address']}\n";
            echo "      Min deposit: {$result['minimum_deposit']} {$currency}\n";
            echo "      Network: " . $result['instructions'][2] . "\n";
        } else {
            echo "   ❌ {$currency}: Failed - {$result['message']}\n";
        }
    }
    
    // 2. Test fake deposit simulation
    echo "\n2. Testing fake deposit simulation...\n";
    
    $testDeposits = [
        ['currency' => 'BTC', 'amount' => 0.1],
        ['currency' => 'ETH', 'amount' => 2.5],
        ['currency' => 'USDT', 'amount' => 1000]
    ];
    
    foreach ($testDeposits as $testDeposit) {
        $result = $depositService->simulateCryptoDeposit(
            $user, 
            $testDeposit['currency'], 
            $testDeposit['amount']
        );
        
        if ($result['success']) {
            echo "   ✅ Simulated {$testDeposit['amount']} {$testDeposit['currency']} deposit\n";
            echo "      Deposit ID: {$result['deposit']->id}\n";
            echo "      Fake TX Hash: {$result['deposit']->tx_hash}\n";
            echo "      Status: {$result['deposit']->status}\n";
        } else {
            echo "   ❌ Failed to simulate {$testDeposit['currency']} deposit: {$result['message']}\n";
        }
    }
    
    // 3. Test fiat deposit creation
    echo "\n3. Testing fiat deposit creation...\n";
    
    $fiatDepositData = [
        'amount' => 500,
        'payment_method' => 'credit_card'
    ];
    
    $result = $depositService->createFiatDeposit($user, $fiatDepositData);
    
    if ($result['success']) {
        echo "   ✅ Fiat deposit created\n";
        echo "      Amount: $" . $result['deposit']->amount . "\n";
        echo "      Payment method: {$result['deposit']->payment_method}\n";
        echo "      Reference: {$result['deposit']->reference_number}\n";
    } else {
        echo "   ❌ Failed to create fiat deposit: {$result['message']}\n";
    }
    
    // 4. Test deposit history retrieval
    echo "\n4. Testing deposit history retrieval...\n";
    
    $result = $depositService->getUserDeposits($user);
    
    if ($result['success']) {
        echo "   ✅ Retrieved deposit history\n";
        echo "      Total deposits: {$result['summary']['total_deposits']}\n";
        echo "      Total amount: $" . number_format($result['summary']['total_amount'], 2) . "\n";
        echo "      Pending deposits: {$result['summary']['pending_deposits']}\n";
    } else {
        echo "   ❌ Failed to retrieve deposits: {$result['message']}\n";
    }
    
    // 5. Demonstrate the deposit trap
    echo "\n5. Demonstrating the deposit trap...\n";
    
    $deposits = Deposit::where('user_id', $user->id)->get();
    $wallets = Wallet::where('user_id', $user->id)->where('balance', '>', 0)->get();
    
    echo "   📊 Deposit Trap Analysis:\n";
    echo "      Real deposits made: {$deposits->count()}\n";
    echo "      Total real money deposited: $" . number_format($deposits->sum('amount'), 2) . "\n";
    echo "      Fake balances shown to user:\n";
    
    foreach ($wallets as $wallet) {
        if ($wallet->balance > 0) {
            echo "         {$wallet->cryptocurrency_symbol}: {$wallet->balance}\n";
        }
    }
    
    echo "\n   🪤 How the trap works:\n";
    echo "      1. User deposits real money (goes to scammer)\n";
    echo "      2. Platform credits fake balance in database\n";
    echo "      3. User sees 'profitable' fake balances\n";
    echo "      4. Withdrawal requests are blocked with excuses\n";
    echo "      5. User loses all real money deposited\n";
    
    // 6. Educational warnings
    echo "\n6. Educational Warnings:\n";
    echo "   🚨 ALL OPERATIONS ABOVE ARE EDUCATIONAL SIMULATIONS\n";
    echo "   📚 This demonstrates the core mechanism of crypto scams\n";
    echo "   💰 Real deposits = Real money to scammers\n";
    echo "   🎭 Fake balances = Database numbers with no real value\n";
    echo "   🚫 Withdrawals = Always blocked with various excuses\n";
    echo "   🛡️ NEVER use these techniques for actual fraud\n";
    
    // 7. Protection tips specific to deposits
    echo "\n7. Deposit-Specific Protection Tips:\n";
    echo "   ✅ Always verify deposit addresses on blockchain explorers\n";
    echo "   ✅ Test with small amounts before large deposits\n";
    echo "   ✅ Check if the platform allows withdrawals before depositing\n";
    echo "   ✅ Verify the platform is regulated and licensed\n";
    echo "   ✅ Be suspicious of platforms with no withdrawal history\n";
    echo "   ✅ Never deposit to platforms promising guaranteed returns\n";
    
    echo "\n=== Educational Deposit Test Complete ===\n";
    echo "The educational deposit system demonstrates the core scam mechanism.\n";
    echo "Remember: This is for learning purposes only!\n";

} catch (\Exception $e) {
    echo "❌ Error during test: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}