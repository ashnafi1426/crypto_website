<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->boot();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

try {
    // Check if user already exists
    $existingUser = User::where('email', 'test@example.com')->first();
    
    if ($existingUser) {
        echo "User already exists with ID: " . $existingUser->id . "\n";
        echo "Email: " . $existingUser->email . "\n";
    } else {
        // Create new user
        $user = new User();
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->password = Hash::make('TestPassword123!');
        $user->email_verified_at = now();
        $user->save();
        
        echo "User created successfully!\n";
        echo "ID: " . $user->id . "\n";
        echo "Email: " . $user->email . "\n";
        echo "Name: " . $user->name . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}