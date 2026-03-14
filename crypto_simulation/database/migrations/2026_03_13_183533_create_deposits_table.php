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
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('currency', 10); // BTC, ETH, USD, etc.
            $table->enum('type', ['crypto', 'fiat'])->default('crypto');
            $table->decimal('amount', 20, 8); // Support up to 8 decimal places
            $table->decimal('fee', 20, 8)->default(0);
            $table->decimal('net_amount', 20, 8); // amount - fee
            
            // Crypto-specific fields
            $table->string('wallet_address')->nullable(); // Generated deposit address
            $table->string('txid')->nullable(); // Transaction ID from blockchain
            $table->integer('confirmations')->default(0);
            $table->integer('required_confirmations')->default(3);
            
            // Fiat-specific fields
            $table->string('payment_method')->nullable(); // bank_transfer, credit_card, paypal
            $table->string('payment_reference')->nullable(); // Bank reference, card transaction ID
            $table->json('payment_details')->nullable(); // Additional payment info
            
            // Status and tracking
            $table->enum('status', ['pending', 'confirming', 'completed', 'failed', 'cancelled'])
                  ->default('pending');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            // Admin and system fields
            $table->text('notes')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users');
            $table->json('metadata')->nullable(); // Additional data
            
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['currency', 'status']);
            $table->index('txid');
            $table->index('wallet_address');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
