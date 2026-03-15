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
        Schema::table('deposit_addresses', function (Blueprint $table) {
            $table->enum('type', ['user_generated', 'metamask', 'admin_treasury'])
                  ->default('user_generated')
                  ->after('address');
            $table->json('metadata')->nullable()->after('last_used_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deposit_addresses', function (Blueprint $table) {
            $table->dropColumn(['type', 'metadata']);
        });
    }
};