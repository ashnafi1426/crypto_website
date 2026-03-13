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
    // Clear cache entries related to rate limiting
    $cacheEntries = Capsule::table('cache')->where('key', 'like', '%rate_limit%')->get();
    
    echo "Found " . $cacheEntries->count() . " rate limit cache entries\n";
    
    foreach ($cacheEntries as $entry) {
        echo "  - " . $entry->key . "\n";
    }
    
    // Delete rate limit cache entries
    $deleted = Capsule::table('cache')->where('key', 'like', '%rate_limit%')->delete();
    
    echo "✅ Cleared {$deleted} rate limit cache entries\n";
    
    // Also clear auth rate limit entries
    $authDeleted = Capsule::table('cache')->where('key', 'like', '%auth_rate_limit%')->delete();
    
    echo "✅ Cleared {$authDeleted} auth rate limit cache entries\n";
    
    echo "\n🎯 Rate limits cleared! You can now login again.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}