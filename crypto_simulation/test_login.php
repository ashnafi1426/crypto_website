<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

// Setup database connection
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => __DIR__ . '/database/database.sqlite',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

try {
    $user = Capsule::table('users')->where('email', 'test@example.com')->first();
    
    if ($user) {
        echo "User found: {$user->email}\n";
        echo "Stored password hash: " . substr($user->password, 0, 20) . "...\n";
        
        $testPassword = 'TestPassword123!';
        $isValid = password_verify($testPassword, $user->password);
        
        echo "Password verification: " . ($isValid ? "✅ VALID" : "❌ INVALID") . "\n";
        
        if (!$isValid) {
            echo "Updating password hash...\n";
            $newHash = password_hash($testPassword, PASSWORD_DEFAULT);
            
            Capsule::table('users')
                ->where('id', $user->id)
                ->update(['password' => $newHash]);
                
            echo "✅ Password updated successfully\n";
        }
    } else {
        echo "❌ User not found\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}