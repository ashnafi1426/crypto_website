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
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('currency', 10); // BTC, ETH, USD, etc.
            $table->enum('type', ['crypto', 'fiat'])->default('crypto');
            $table->decimal('amount', 20, 8); // Requested withdrawal amount
            $table->decimal('fee', 20, 8)->default(0); // Network/processing fee
            $table->decimal('net_amount', 20, 8); // amount - fee (what user receives)
            
            // Crypto-specific fields
            $table->string('to_address')->nullable(); // Destination wallet address
            $table->string('txid')->nullable(); // Transaction ID from blockchain
            $table->integer('confirmations')->default(0);
            $table->decimal('network_fee', 20, 8)->nullable(); // Actual network fee paid
            
            // Fiat-specific fields
            $table->string('payment_method')->nullable(); // bank_transfer, paypal, etc.
            $table->json('payment_details')->nullable(); // Bank account, PayPal email, etc.
            $table->string('payment_reference')->nullable(); // Bank reference number
            
            // Security and verification
            $table->boolean('two_factor_verified')->default(false);
            $table->boolean('email_verified')->default(false);
            $table->string('verification_code')->nullable();
            $table->timestamp('verification_expires_at')->nullable();
            
            // Status and tracking
            $table->enum('status', [
                'pending',           // Awaiting verification
                'verified',          // User verified, awaiting admin approval
                'approved',          // Admin approved, processing
                'processing',        // Being processed (sent to blockchain/bank)
                'completed',         // Successfully completed
                'failed',            // Failed to process
                'cancelled',         // Cancelled by user or admin
                'rejected'           // Rejected by admin
            ])->default('pending');
            
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            // Admin and system fields
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('processed_by')->nullable()->constrained('users');
            $table->text('admin_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('metadata')->nullable(); // Additional data
            
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['currency', 'status']);
            $table->index('txid');
            $table->index('to_address');
            $table->index('verification_code');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
