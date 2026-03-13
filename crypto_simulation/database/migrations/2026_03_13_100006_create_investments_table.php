<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('cryptocurrency_symbol');
            $table->enum('investment_type', ['staking', 'savings', 'liquidity_mining', 'yield_farming']);
            $table->decimal('amount', 20, 8);
            $table->integer('duration_days');
            $table->decimal('expected_return_rate', 5, 4); // Annual percentage
            $table->decimal('current_value', 20, 8);
            $table->enum('status', ['active', 'completed', 'cancelled', 'matured'])->default('active');
            $table->timestamp('started_at');
            $table->timestamp('maturity_date');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['cryptocurrency_symbol', 'status']);
            $table->foreign('cryptocurrency_symbol')->references('symbol')->on('cryptocurrencies');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};