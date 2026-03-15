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
        Schema::table('cryptocurrencies', function (Blueprint $table) {
            $table->decimal('market_cap', 20, 2)->default('0.00')->after('current_price');
            $table->decimal('volume_24h', 20, 2)->default('0.00')->after('market_cap');
            $table->decimal('price_change_24h', 20, 8)->default('0.00000000')->after('volume_24h');
            $table->decimal('price_change_percentage_24h', 10, 4)->default('0.0000')->after('price_change_24h');
            $table->decimal('circulating_supply', 20, 2)->nullable()->after('price_change_percentage_24h');
            $table->string('logo_url')->nullable()->after('circulating_supply');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cryptocurrencies', function (Blueprint $table) {
            $table->dropColumn([
                'market_cap',
                'volume_24h', 
                'price_change_24h',
                'price_change_percentage_24h',
                'circulating_supply',
                'logo_url'
            ]);
        });
    }
};