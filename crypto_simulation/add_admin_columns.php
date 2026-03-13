<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

try {
    Schema::table('users', function (Blueprint $table) {
        if (!Schema::hasColumn('users', 'status')) {
            $table->enum('status', ['active', 'suspended', 'inactive'])->default('active');
        }
        if (!Schema::hasColumn('users', 'kyc_status')) {
            $table->enum('kyc_status', ['pending', 'approved', 'rejected', 'not_submitted'])->default('not_submitted');
        }
        if (!Schema::hasColumn('users', 'kyc_approved_at')) {
            $table->timestamp('kyc_approved_at')->nullable();
        }
        if (!Schema::hasColumn('users', 'referred_by')) {
            $table->unsignedBigInteger('referred_by')->nullable();
        }
    });
    
    echo "✅ Admin columns added successfully!\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}