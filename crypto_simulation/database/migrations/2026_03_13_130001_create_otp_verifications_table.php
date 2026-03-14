<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('identifier'); // email or phone number
            $table->string('otp_code', 6);
            $table->string('type'); // email, sms, voice
            $table->string('purpose'); // registration, login, password_reset, transaction
            $table->timestamp('expires_at');
            $table->boolean('is_used')->default(false);
            $table->timestamp('used_at')->nullable();
            $table->integer('attempts')->default(0);
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            
            $table->index(['identifier', 'type', 'purpose']);
            $table->index(['otp_code', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_verifications');
    }
};