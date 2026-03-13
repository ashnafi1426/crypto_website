<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('referral_code')->unique();
            $table->decimal('commission_rate', 5, 4)->default(0.05); // 5% default
            $table->integer('total_referrals')->default(0);
            $table->integer('active_referrals')->default(0);
            $table->decimal('total_earned', 20, 8)->default(0);
            $table->decimal('pending_payout', 20, 8)->default(0);
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['referral_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_programs');
    }
};