<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

echo "=== Account Unlock Tool ===\n\n";

// Get email from command line or use default
$email = $argv[1] ?? null;

if (!$email) {
    echo "Usage: php unlock_account.php <email>\n";
    echo "Or unlock all accounts: php unlock_account.php all\n\n";
    
    // Show locked accounts
    $lockedUsers = User::where('locked_until', '>', now())->get();
    
    if ($lockedUsers->count() > 0) {
        echo "Currently locked accounts:\n";
        foreach ($lockedUsers as $user) {
            echo "  - {$user->email} (locked until {$user->locked_until})\n";
        }
        echo "\n";
    } else {
        echo "No locked accounts found.\n";
    }
    
    exit(0);
}

if ($email === 'all') {
    // Unlock all accounts
    $count = User::where('locked_until', '>', now())
        ->update([
            'locked_until' => null,
            'failed_login_attempts' => 0
        ]);
    
    echo "✅ Unlocked {$count} account(s)\n";
} else {
    // Unlock specific account
    $user = User::where('email', $email)->first();
    
    if (!$user) {
        echo "❌ User not found: {$email}\n";
        exit(1);
    }
    
    $user->locked_until = null;
    $user->failed_login_attempts = 0;
    $user->save();
    
    echo "✅ Account unlocked: {$email}\n";
    echo "   Failed attempts reset to 0\n";
}

echo "\n🎯 You can now login!\n";
