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
        Schema::create('cryptocurrencies', function (Blueprint $table) {
            $table->string('symbol', 10)->primary();
            $table->string('name', 100);
            $table->decimal('current_price', 20, 8)->default(0);
            $table->float('volatility')->default(0.02);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Index for performance
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cryptocurrencies');
    }
};
