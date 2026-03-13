<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

echo "=== Current Users in Database ===\n\n";

$users = User::all(['id', 'name', 'email', 'is_admin', 'status', 'kyc_status']);

if ($users->isEmpty()) {
    echo "No users found in database.\n";
} else {
    foreach ($users as $user) {
        echo "ID: {$user->id}\n";
        echo "Name: {$user->name}\n";
        echo "Email: {$user->email}\n";
        echo "Admin: " . ($user->is_admin ? 'Yes' : 'No') . "\n";
        echo "Status: {$user->status}\n";
        echo "KYC Status: {$user->kyc_status}\n";
        echo "---\n";
    }
}

echo "\n=== Admin Users ===\n";
$admins = User::where('is_admin', true)->get(['name', 'email']);
foreach ($admins as $admin) {
    echo "Admin: {$admin->name} ({$admin->email})\n";
}

echo "\n=== Login Credentials ===\n";
echo "Admin Email: admin@cryptoexchange.com\n";
echo "Admin Password: admin123\n";