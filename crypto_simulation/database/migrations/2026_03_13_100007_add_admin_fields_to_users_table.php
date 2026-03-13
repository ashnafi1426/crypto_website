<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
            $table->integer('failed_login_attempts')->default(0)->after('is_admin');
            $table->enum('status', ['active', 'suspended', 'inactive'])->default('active')->after('failed_login_attempts');
            $table->enum('kyc_status', ['pending', 'approved', 'rejected', 'not_submitted'])->default('not_submitted')->after('status');
            $table->timestamp('kyc_approved_at')->nullable()->after('kyc_status');
            $table->timestamp('locked_until')->nullable()->after('kyc_approved_at');
            $table->foreignId('referred_by')->nullable()->constrained('users')->after('locked_until');
            
            $table->index(['status']);
            $table->index(['kyc_status']);
            $table->index(['referred_by']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['referred_by']);
            $table->dropIndex(['status']);
            $table->dropIndex(['kyc_status']);
            $table->dropIndex(['referred_by']);
            $table->dropColumn([
                'is_admin',
                'failed_login_attempts',
                'status',
                'kyc_status',
                'kyc_approved_at',
                'locked_until',
                'referred_by'
            ]);
        });
    }
};