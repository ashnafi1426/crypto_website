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
        Schema::create('investment_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100); // Starter, Pro, Premium, etc.
            $table->string('description', 500)->nullable();
            $table->enum('crypto_type', ['BTC', 'ETH', 'USDT', 'BNB', 'SOL', 'LTC', 'ADA', 'DOT']);
            $table->decimal('min_amount', 20, 8); // Minimum investment amount
            $table->decimal('max_amount', 20, 8); // Maximum investment amount
            $table->decimal('roi_percentage', 5, 2); // Daily ROI percentage (e.g., 5.00%)
            $table->integer('duration_days'); // Investment duration in days
            $table->enum('type', ['daily', 'weekly', 'monthly', 'fixed'])->default('daily');
            $table->boolean('active')->default(true);
            $table->json('features')->nullable(); // Additional features like early withdrawal, compound interest
            $table->integer('max_investors')->nullable(); // Maximum number of investors (for limited plans)
            $table->integer('current_investors')->default(0);
            $table->decimal('total_invested', 30, 8)->default(0);
            $table->timestamps();
            
            // Indexes
            $table->index(['active', 'crypto_type']);
            $table->index('roi_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investment_plans');
    }
};