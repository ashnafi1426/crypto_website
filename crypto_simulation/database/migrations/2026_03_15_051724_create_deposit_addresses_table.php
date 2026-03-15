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
        Schema::create('deposit_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('currency', 10); // BTC, ETH, USDT, etc.
            $table->string('network', 50); // Ethereum, BSC, Bitcoin, etc.
            $table->string('address', 255); // Wallet address from MetaMask
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            
            // Ensure one address per user per currency per network
            $table->unique(['user_id', 'currency', 'network']);
            
            // Index for faster lookups
            $table->index(['currency', 'network']);
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposit_addresses');
    }
};
