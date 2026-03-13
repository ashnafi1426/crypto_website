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
        Schema::create('price_history', function (Blueprint $table) {
            $table->id();
            $table->string('cryptocurrency_symbol', 10);
            $table->decimal('open_price', 20, 8);
            $table->decimal('high_price', 20, 8);
            $table->decimal('low_price', 20, 8);
            $table->decimal('close_price', 20, 8);
            $table->bigInteger('volume')->default(0);
            $table->timestamp('timestamp');
            
            // Foreign key constraint
            $table->foreign('cryptocurrency_symbol')->references('symbol')->on('cryptocurrencies');
            
            // Unique constraint and indexes
            $table->unique(['cryptocurrency_symbol', 'timestamp']);
            $table->index(['cryptocurrency_symbol', 'timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_history');
    }
};
