<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Wallet;
use App\Models\Cryptocurrency;
use Illuminate\Support\Facades\Hash;

echo "=== Creating Additional Admin Users ===\n\n";

$additionalAdmins = [
    [
        'name' => 'Super Admin',
        'email' => 'superadmin@cryptoexchange.com',
        'password' => 'superadmin123',
        'role' => 'Super Administrator'
    ],
    [
        'name' => 'Finance Admin',
        'email' => 'finance@cryptoexchange.com',
        'password' => 'finance123',
        'role' => 'Finance Administrator'
    ],
    [
        'name' => 'Support Admin',
        'email' => 'support@cryptoexchange.com',
        'password' => 'support123',
        'role' => 'Support Administrator'
    ],
    [
        'name' => 'Security Admin',
        'email' => 'security@cryptoexchange.com',
        'password' => 'security123',
        'role' => 'Security Administrator'
    ]
];

foreach ($additionalAdmins as $adminData) {
    // Check if admin already exists
    $existingAdmin = User::where('email', $adminData['email'])->first();
    
    if ($existingAdmin) {
        echo "⚠️  Admin already exists: {$adminData['email']}\n";
        continue;
    }
    
    // Create admin user
    $admin = User::create([
        'name' => $adminData['name'],
        'email' => $adminData['email'],
        'password' => Hash::make($adminData['password']),
        'email_verified_at' => now(),
        'is_admin' => true,
        'status' => 'active',
        'kyc_status' => 'approved',
        'kyc_approved_at' => now(),
    ]);
    
    // Create wallets for admin user
    $cryptos = Cryptocurrency::all();
    foreach ($cryptos as $crypto) {
        Wallet::create([
            'user_id' => $admin->id,
            'cryptocurrency_symbol' => $crypto->symbol,
            'balance' => 500000, // Large balance for testing
            'reserved_balance' => 0,
        ]);
    }
    
    echo "✅ Created {$adminData['role']}: {$adminData['email']} / {$adminData['password']}\n";
}

echo "\n=== All Admin Users ===\n";
$admins = User::where('is_admin', true)->get(['name', 'email']);
foreach ($admins as $admin) {
    echo "👤 {$admin->name} - {$admin->email}\n";
}

echo "\n=== Admin Login Credentials ===\n";
echo "1. Main Admin:\n";
echo "   Email: admin@cryptoexchange.com\n";
echo "   Password: admin123\n\n";

echo "2. Super Admin:\n";
echo "   Email: superadmin@cryptoexchange.com\n";
echo "   Password: superadmin123\n\n";

echo "3. Finance Admin:\n";
echo "   Email: finance@cryptoexchange.com\n";
echo "   Password: finance123\n\n";

echo "4. Support Admin:\n";
echo "   Email: support@cryptoexchange.com\n";
echo "   Password: support123\n\n";

echo "5. Security Admin:\n";
echo "   Email: security@cryptoexchange.com\n";
echo "   Password: security123\n\n";

echo "🌐 Server URL: http://127.0.0.1:8000\n";
echo "🔐 Admin Panel: http://127.0.0.1:8000/api/admin/dashboard\n";