<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Database configuration
$capsule = new DB;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'database' => $_ENV['DB_DATABASE'] ?? 'crypto_simulation',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

try {
    echo "Marking all users as email verified...\n";
    
    // Update all users to have email_verified_at timestamp
    $updated = DB::table('users')
        ->whereNull('email_verified_at')
        ->update([
            'email_verified_at' => now()
        ]);
    
    echo "Updated {$updated} users with email verification timestamp.\n";
    
    // Show current user status
    $users = DB::table('users')
        ->select('id', 'name', 'email', 'email_verified_at', 'created_at')
        ->get();
    
    echo "\nCurrent user verification status:\n";
    echo "ID | Name | Email | Verified At | Created At\n";
    echo "---|------|-------|-------------|------------\n";
    
    foreach ($users as $user) {
        echo sprintf(
            "%d | %s | %s | %s | %s\n",
            $user->id,
            $user->name,
            $user->email,
            $user->email_verified_at ?? 'NULL',
            $user->created_at
        );
    }
    
    echo "\nAll users are now marked as email verified!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}