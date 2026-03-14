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
        Schema::create('user_investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('investment_plans')->onDelete('cascade');
            $table->decimal('amount', 20, 8); // Investment amount
            $table->decimal('daily_profit', 20, 8)->default(0); // Daily profit amount
            $table->decimal('total_profit', 20, 8)->default(0); // Total profit earned
            $table->decimal('total_withdrawn', 20, 8)->default(0); // Total amount withdrawn
            $table->timestamp('start_date'); // Investment start date
            $table->timestamp('end_date'); // Investment end date
            $table->timestamp('last_profit_date')->nullable(); // Last profit distribution date
            $table->enum('status', ['active', 'completed', 'cancelled', 'paused'])->default('active');
            $table->boolean('auto_reinvest')->default(false); // Auto-reinvest profits
            $table->json('metadata')->nullable(); // Additional investment data
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['status', 'last_profit_date']);
            $table->index('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_investments');
    }
};