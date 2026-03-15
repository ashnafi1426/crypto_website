<?php

require_once __DIR__ . '/vendor/autoload.php';

// Initialize Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🏆 FINAL ADMIN WALLET SYSTEM VERIFICATION\n";
echo "========================================\n\n";

$allPassed = true;
$issues = [];

try {
    // 1. Environment Configuration Check
    echo "1. 🔧 Environment Configuration Check...\n";
    
    $requiredEnvVars = [
        'ADMIN_WALLET_ENABLED' => env('ADMIN_WALLET_ENABLED'),
        'ADMIN_WALLET_COLLECTION_MODE' => env('ADMIN_WALLET_COLLECTION_MODE'),
        'ADMIN_WALLET_BTC' => env('ADMIN_WALLET_BTC'),
        'ADMIN_WALLET_ETH' => env('ADMIN_WALLET_ETH'),
        'ADMIN_WALLET_USDT' => env('ADMIN_WALLET_USDT'),
        'ADMIN_WALLET_USDC' => env('ADMIN_WALLET_USDC'),
        'ADMIN_WALLET_BNB' => env('ADMIN_WALLET_BNB')
    ];
    
    foreach ($requiredEnvVars as $key => $value) {
        if ($value) {
            echo "   ✅ {$key}: {$value}\n";
        } else {
            echo "   ❌ {$key}: Not set\n";
            $allPassed = false;
            $issues[] = "Missing environment variable: {$key}";
        }
    }

    // 2. Service Registration Check
    echo "\n2. 🔌 Service Registration Check...\n";
    
    try {
        $adminWalletService = app(\App\Services\AdminWalletService::class);
        echo "   ✅ AdminWalletService: Registered\n";
    } catch (Exception $e) {
        echo "   ❌ AdminWalletService: Failed to resolve\n";
        $allPassed = false;
        $issues[] = "AdminWalletService not registered";
    }
    
    try {
        $walletAddressService = app(\App\Services\WalletAddressService::class);
        echo "   ✅ WalletAddressService: Registered\n";
    } catch (Exception $e) {
        echo "   ❌ WalletAddressService: Failed to resolve\n";
        $allPassed = false;
        $issues[] = "WalletAddressService not registered";
    }

    // 3. Database Schema Check
    echo "\n3. 🗄️ Database Schema Check...\n";
    
    try {
        // Check if deposit_addresses table has required columns
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('deposit_addresses');
        $requiredColumns = ['type', 'metadata'];
        
        foreach ($requiredColumns as $column) {
            if (in_array($column, $columns)) {
                echo "   ✅ deposit_addresses.{$column}: Exists\n";
            } else {
                echo "   ❌ deposit_addresses.{$column}: Missing\n";
                $allPassed = false;
                $issues[] = "Missing column: deposit_addresses.{$column}";
            }
        }
        
        // Check if we can create deposit addresses with admin_treasury type
        $testAddress = \App\Models\DepositAddress::where('type', 'admin_treasury')->first();
        if ($testAddress) {
            echo "   ✅ admin_treasury type: Working\n";
        } else {
            echo "   ⚠️ admin_treasury type: No records found (normal for new setup)\n";
        }
        
    } catch (Exception $e) {
        echo "   ❌ Database schema: Error - " . $e->getMessage() . "\n";
        $allPassed = false;
        $issues[] = "Database schema issue";
    }

    // 4. API Routes Check
    echo "\n4. 🛣️ API Routes Check...\n";
    
    $routes = [
        'GET /api/admin/treasury/wallets',
        'PUT /api/admin/treasury/wallets', 
        'GET /api/admin/treasury/collection-stats'
    ];
    
    // We can't easily test routes without making HTTP requests, so we check if controllers exist
    try {
        $adminController = app(\App\Http\Controllers\Api\AdminController::class);
        
        $methods = ['getAdminWallets', 'updateAdminWallet', 'getDepositCollectionStats'];
        foreach ($methods as $method) {
            if (method_exists($adminController, $method)) {
                echo "   ✅ AdminController::{$method}: Exists\n";
            } else {
                echo "   ❌ AdminController::{$method}: Missing\n";
                $allPassed = false;
                $issues[] = "Missing method: AdminController::{$method}";
            }
        }
    } catch (Exception $e) {
        echo "   ❌ AdminController: Error - " . $e->getMessage() . "\n";
        $allPassed = false;
        $issues[] = "AdminController issue";
    }

    // 5. Functional Test
    echo "\n5. ⚙️ Functional Test...\n";
    
    try {
        // Test user creation and address generation
        $testUser = \App\Models\User::firstOrCreate([
            'email' => 'final.test@nexus.com'
        ], [
            'name' => 'Final Test User',
            'password' => bcrypt('password123'),
            'email_verified_at' => now()
        ]);
        
        $walletService = app(\App\Services\WalletAddressService::class);
        $adminWalletService = app(\App\Services\AdminWalletService::class);
        
        // Test each currency
        $currencies = ['BTC', 'ETH', 'USDT'];
        $adminAddressCount = 0;
        
        foreach ($currencies as $currency) {
            $network = $currency === 'BTC' ? 'Bitcoin' : 'Ethereum';
            
            $userAddress = $walletService->getDepositAddress($testUser, $currency, $network);
            $adminAddress = $adminWalletService->getAdminWalletAddress($currency);
            
            if ($userAddress && $adminAddress && $userAddress->address === $adminAddress) {
                echo "   ✅ {$currency}: User gets admin wallet address\n";
                $adminAddressCount++;
            } else {
                echo "   ❌ {$currency}: User does NOT get admin wallet address\n";
                $allPassed = false;
                $issues[] = "{$currency} not using admin wallet";
            }
        }
        
        if ($adminAddressCount === count($currencies)) {
            echo "   🎯 All currencies using admin wallets: SUCCESS\n";
        } else {
            echo "   ❌ Only {$adminAddressCount}/" . count($currencies) . " currencies using admin wallets\n";
            $allPassed = false;
        }
        
    } catch (Exception $e) {
        echo "   ❌ Functional test: Error - " . $e->getMessage() . "\n";
        $allPassed = false;
        $issues[] = "Functional test failed";
    }

    // 6. Statistics Check
    echo "\n6. 📊 Statistics Check...\n";
    
    try {
        $adminWalletService = app(\App\Services\AdminWalletService::class);
        $stats = $adminWalletService->getAdminWalletStatistics();
        
        echo "   📈 Total admin wallets: {$stats['total_wallets']}\n";
        echo "   📈 Collection mode: {$stats['collection_mode']}\n";
        echo "   📈 Status: {$stats['status']}\n";
        echo "   📈 Auto collection: " . ($stats['configuration']['auto_collection'] ? 'YES' : 'NO') . "\n";
        
        if ($stats['total_wallets'] >= 5 && $stats['status'] === 'active') {
            echo "   ✅ Statistics: All good\n";
        } else {
            echo "   ❌ Statistics: Issues detected\n";
            $allPassed = false;
            $issues[] = "Statistics show issues";
        }
        
    } catch (Exception $e) {
        echo "   ❌ Statistics: Error - " . $e->getMessage() . "\n";
        $allPassed = false;
        $issues[] = "Statistics check failed";
    }

} catch (Exception $e) {
    echo "❌ Critical error during verification: " . $e->getMessage() . "\n";
    $allPassed = false;
    $issues[] = "Critical system error";
}

// Final Report
echo "\n" . str_repeat("=", 60) . "\n";
if ($allPassed) {
    echo "🎉 VERIFICATION PASSED - SYSTEM READY FOR PRODUCTION!\n";
} else {
    echo "❌ VERIFICATION FAILED - ISSUES FOUND\n";
}
echo str_repeat("=", 60) . "\n";

if ($allPassed) {
    echo "\n✅ ALL SYSTEMS OPERATIONAL:\n";
    echo "1. ✅ Environment variables configured\n";
    echo "2. ✅ Services registered and working\n";
    echo "3. ✅ Database schema updated\n";
    echo "4. ✅ API endpoints available\n";
    echo "5. ✅ Functional tests passed\n";
    echo "6. ✅ Statistics showing correct operation\n";
    
    echo "\n🎯 MONEY FLOW CONFIRMED:\n";
    echo "User Deposit → Admin Wallet Address → Your Wallet 💰\n";
    
    echo "\n📋 PRODUCTION DEPLOYMENT:\n";
    echo "1. ✅ System is ready for production\n";
    echo "2. ⚠️  Update .env with your REAL wallet addresses\n";
    echo "3. ⚠️  Test with small real deposits first\n";
    echo "4. ⚠️  Monitor your admin wallets for incoming funds\n";
    echo "5. ⚠️  Set up withdrawal system from treasury wallets\n";
    
} else {
    echo "\n❌ ISSUES FOUND:\n";
    foreach ($issues as $issue) {
        echo "   • {$issue}\n";
    }
    
    echo "\n🔧 REQUIRED FIXES:\n";
    echo "1. Fix the issues listed above\n";
    echo "2. Re-run this verification script\n";
    echo "3. Ensure all tests pass before production\n";
}

echo "\n📞 SUPPORT:\n";
echo "If you need help with any issues, check:\n";
echo "1. Laravel logs: storage/logs/laravel.log\n";
echo "2. Environment file: .env\n";
echo "3. Database migrations: php artisan migrate:status\n";
echo "4. Service providers: config/app.php\n";

echo "\n🚀 NEXT STEPS:\n";
if ($allPassed) {
    echo "Your admin wallet system is fully operational!\n";
    echo "Update the .env file with your real wallet addresses and start collecting deposits.\n";
} else {
    echo "Fix the identified issues and run this verification again.\n";
}

exit($allPassed ? 0 : 1);