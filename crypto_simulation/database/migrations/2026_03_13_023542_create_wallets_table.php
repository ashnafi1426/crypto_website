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
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('cryptocurrency_symbol', 10);
            $table->decimal('balance', 20, 8)->default(0);
            $table->decimal('reserved_balance', 20, 8)->default(0);
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('cryptocurrency_symbol')->references('symbol')->on('cryptocurrencies');
            
            // Unique constraint and indexes
            $table->unique(['user_id', 'cryptocurrency_symbol']);
            $table->index(['user_id', 'balance']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
