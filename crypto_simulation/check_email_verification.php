<?php

require_once 'vendor/autoload.php';

// Load Laravel application
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "=== User Email Verification Status ===\n\n";

$users = User::select('id', 'name', 'email', 'email_verified_at', 'is_admin')->get();

if ($users->isEmpty()) {
    echo "No users found in the database.\n";
} else {
    foreach ($users as $user) {
        echo "ID: {$user->id}\n";
        echo "Name: {$user->name}\n";
        echo "Email: {$user->email}\n";
        echo "Email Verified: " . ($user->email_verified_at ? "Yes ({$user->email_verified_at})" : "No") . "\n";
        echo "Is Admin: " . ($user->is_admin ? "Yes" : "No") . "\n";
        echo "---\n";
    }
}

echo "\n=== Quick Fix: Mark All Users as Email Verified ===\n";
echo "Do you want to mark all users as email verified? (y/n): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim($line) === 'y' || trim($line) === 'Y') {
    $updated = User::whereNull('email_verified_at')->update([
        'email_verified_at' => now()
    ]);
    
    echo "Updated {$updated} users to have verified emails.\n";
    echo "All users should now be able to access the dashboard without email verification.\n";
} else {
    echo "No changes made.\n";
}

echo "\nDone!\n";