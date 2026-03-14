<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->change();
            $table->string('email_verification_token')->nullable();
            $table->timestamp('email_verification_sent_at')->nullable();
            $table->integer('email_verification_attempts')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'email_verification_token',
                'email_verification_sent_at',
                'email_verification_attempts'
            ]);
        });
    }
};