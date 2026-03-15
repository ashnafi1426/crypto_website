<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Educational scam simulation fields
            $table->boolean('withdrawal_blocked')->default(false);
            $table->string('block_reason')->nullable();
            $table->timestamp('blocked_at')->nullable();
            
            // Add index for performance
            $table->index('withdrawal_blocked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['withdrawal_blocked']);
            $table->dropColumn(['withdrawal_blocked', 'block_reason', 'blocked_at']);
        });
    }
};