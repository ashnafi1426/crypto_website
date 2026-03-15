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
        Schema::create('markets', function (Blueprint $table) {
            $table->id();
            $table->string('base_coin', 10);
            $table->string('quote_coin', 10);
            $table->decimal('price', 20, 8)->default('0.00000000');
            $table->decimal('volume_24h', 20, 2)->default('0.00');
            $table->decimal('change_24h', 10, 4)->default('0.0000');
            $table->decimal('high_24h', 20, 8)->default('0.00000000');
            $table->decimal('low_24h', 20, 8)->default('0.00000000');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('base_coin')->references('symbol')->on('cryptocurrencies');
            $table->foreign('quote_coin')->references('symbol')->on('cryptocurrencies');

            // Unique constraint for trading pairs
            $table->unique(['base_coin', 'quote_coin']);
            
            // Indexes for performance
            $table->index(['base_coin', 'quote_coin']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('markets');
    }
};