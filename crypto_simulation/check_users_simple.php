<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "Checking users in database...\n\n";

try {
    $users = User::all(['id', 'name', 'email', 'is_admin', 'email_verified_at']);
    
    if ($users->count() > 0) {
        echo "Found " . $users->count() . " users:\n";
        echo "ID | Name | Email | Admin | Verified\n";
        echo "---|------|-------|-------|----------\n";
        
        foreach ($users as $user) {
            echo sprintf(
                "%d | %s | %s | %s | %s\n",
                $user->id,
                $user->name,
                $user->email,
                $user->is_admin ? 'Yes' : 'No',
                $user->email_verified_at ? 'Yes' : 'No'
            );
        }
    } else {
        echo "No users found in database.\n";
        echo "Creating a test user...\n";
        
        $testUser = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
            'is_admin' => false
        ]);
        
        echo "Test user created: {$testUser->email}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nDone.\n";