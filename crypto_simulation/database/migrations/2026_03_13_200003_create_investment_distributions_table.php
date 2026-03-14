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
        Schema::create('investment_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained('user_investments')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 20, 8); // Profit amount distributed
            $table->enum('type', ['daily_profit', 'bonus', 'compound', 'final_payout'])->default('daily_profit');
            $table->date('distribution_date'); // Date of profit distribution
            $table->enum('status', ['pending', 'completed', 'failed'])->default('completed');
            $table->string('reference_id', 100)->unique(); // Unique reference for tracking
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['investment_id', 'distribution_date']);
            $table->index(['user_id', 'distribution_date']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investment_distributions');
    }
};