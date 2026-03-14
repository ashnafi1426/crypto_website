<?php

/**
 * Make OAuth User Admin
 * Usage: php make_oauth_user_admin.php user@gmail.com
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

if ($argc < 2) {
    echo "Usage: php make_oauth_user_admin.php <email>\n";
    echo "Example: php make_oauth_user_admin.php user@gmail.com\n";
    exit(1);
}

$email = $argv[1];

echo "=== Making OAuth User Admin ===\n\n";

// Find user by email
$user = User::where('email', $email)->first();

if (!$user) {
    echo "❌ User not found: {$email}\n";
    echo "\nAvailable OAuth users:\n";
    
    $oauthUsers = User::whereIn('provider', ['google', 'apple'])->get();
    if ($oauthUsers->count() > 0) {
        foreach ($oauthUsers as $oauthUser) {
            echo "   - {$oauthUser->name} ({$oauthUser->email}) - Provider: {$oauthUser->provider}\n";
        }
    } else {
        echo "   No OAuth users found.\n";
    }
    exit(1);
}

echo "Found user:\n";
echo "   Name: {$user->name}\n";
echo "   Email: {$user->email}\n";
echo "   Provider: " . ($user->provider ?? 'local') . "\n";
echo "   Current Admin Status: " . ($user->is_admin ? 'Yes' : 'No') . "\n";
echo "   Created: {$user->created_at}\n\n";

if ($user->is_admin) {
    echo "✅ User is already an admin!\n";
} else {
    // Make user admin
    $user->update(['is_admin' => true]);
    echo "✅ User has been made an admin!\n";
    
    // Verify the change
    $user->refresh();
    if ($user->is_admin) {
        echo "✅ Admin status confirmed in database\n";
    } else {
        echo "❌ Failed to update admin status\n";
    }
}

echo "\n=== Admin Access Instructions ===\n";
echo "1. User can now access admin panel at: http://localhost:5175/admin\n";
echo "2. After OAuth login, user will be redirected to admin panel\n";
echo "3. Admin features include:\n";
echo "   - User management\n";
echo "   - Trading oversight\n";
echo "   - Analytics dashboard\n";
echo "   - System settings\n\n";

echo "✅ Complete!\n";