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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('cryptocurrency_symbol', 10);
            $table->enum('order_type', ['market', 'limit']);
            $table->enum('side', ['buy', 'sell']);
            $table->decimal('quantity', 20, 8);
            $table->decimal('price', 20, 8)->nullable();
            $table->decimal('filled_quantity', 20, 8)->default(0);
            $table->enum('status', ['pending', 'partial', 'filled', 'cancelled'])->default('pending');
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('cryptocurrency_symbol')->references('symbol')->on('cryptocurrencies');
            
            // Indexes for performance
            $table->index(['cryptocurrency_symbol', 'side', 'price', 'created_at']);
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
