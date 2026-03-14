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
        Schema::create('oauth_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('state')->unique(); // OAuth state parameter for CSRF protection
            $table->string('provider'); // google, apple
            $table->string('redirect_url')->nullable();
            $table->json('data')->nullable(); // Store additional OAuth data
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['state', 'provider']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_sessions');
    }
};