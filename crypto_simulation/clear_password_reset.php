<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Clearing Password Reset Tokens ===\n\n";

try {
    // Check existing tokens
    $tokens = DB::table('password_reset_tokens')->get();
    
    echo "Current password reset tokens:\n";
    foreach ($tokens as $token) {
        echo "  Email: {$token->email}\n";
        echo "  Created: {$token->created_at}\n";
        echo "  Token: " . substr($token->token, 0, 20) . "...\n\n";
    }
    
    // Clear all tokens
    $deleted = DB::table('password_reset_tokens')->delete();
    
    echo "✅ Cleared {$deleted} password reset tokens\n";
    echo "You can now request a new password reset.\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}