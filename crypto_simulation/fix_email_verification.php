<?php

require_once 'vendor/autoload.php';

// Load Laravel application
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "=== Fixing Email Verification for All Users ===\n\n";

// Count unverified users
$unverifiedCount = User::whereNull('email_verified_at')->count();
echo "Found {$unverifiedCount} users without email verification.\n";

if ($unverifiedCount > 0) {
    // Mark all users as email verified
    $updated = User::whereNull('email_verified_at')->update([
        'email_verified_at' => now()
    ]);
    
    echo "✅ Successfully updated {$updated} users to have verified emails.\n";
    echo "✅ All users can now access the dashboard without email verification.\n";
} else {
    echo "✅ All users already have verified emails.\n";
}

echo "\n=== Verification Complete ===\n";
echo "Users should now be able to login and access the dashboard directly.\n";