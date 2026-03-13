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
        Schema::create('transaction_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('cryptocurrency_symbol', 10);
            $table->decimal('amount', 20, 8);
            $table->enum('transaction_type', ['deposit', 'withdrawal', 'trade_buy', 'trade_sell', 'admin_adjustment']);
            $table->string('reason', 255);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            
            // Foreign key constraint
            $table->foreign('cryptocurrency_symbol')->references('symbol')->on('cryptocurrencies');
            
            // Indexes for performance
            $table->index(['user_id', 'created_at']);
            $table->index(['cryptocurrency_symbol', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_records');
    }
};
