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
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buy_order_id')->constrained('orders');
            $table->foreignId('sell_order_id')->constrained('orders');
            $table->string('cryptocurrency_symbol', 10);
            $table->decimal('quantity', 20, 8);
            $table->decimal('price', 20, 8);
            $table->timestamp('executed_at');
            
            // Foreign key constraint
            $table->foreign('cryptocurrency_symbol')->references('symbol')->on('cryptocurrencies');
            
            // Indexes for performance
            $table->index(['cryptocurrency_symbol', 'executed_at']);
            $table->index('executed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
