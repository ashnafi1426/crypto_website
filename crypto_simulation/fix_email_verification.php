<?php

/**
 * Fix Email Verification for OAuth Users
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "=== Fixing Email Verification ===\n\n";

// Fix OAuth users email verification
$oauthUsers = User::whereIn('provider', ['google', 'apple'])->get();

foreach ($oauthUsers as $user) {
    echo "Fixing: {$user->name} ({$user->email})\n";
    
    // Force update email_verified_at
    $user->email_verified_at = now();
    $user->save();
    
    // Verify the update
    $user->refresh();
    echo "   Email verified: " . ($user->email_verified_at ? 'Yes' : 'No') . "\n";
    echo "   Verified at: " . $user->email_verified_at . "\n\n";
}

echo "✅ Email verification fixed for all OAuth users\n";