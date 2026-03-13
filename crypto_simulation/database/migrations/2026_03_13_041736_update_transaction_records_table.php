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
        Schema::table('transaction_records', function (Blueprint $table) {
            // Add new fields needed by WalletManager
            $table->decimal('balance_before', 20, 8)->nullable()->after('amount');
            $table->decimal('balance_after', 20, 8)->nullable()->after('balance_before');
            $table->string('description', 255)->nullable()->after('reason');
            $table->string('reference_id', 255)->nullable()->after('description');
            
            // Update transaction_type enum to include new types
            $table->dropColumn('transaction_type');
        });
        
        // Add the updated enum column
        Schema::table('transaction_records', function (Blueprint $table) {
            $table->enum('transaction_type', [
                'deposit', 'withdrawal', 'trade_buy', 'trade_sell', 'admin_adjustment',
                'credit', 'debit', 'reserve', 'release'
            ])->after('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_records', function (Blueprint $table) {
            $table->dropColumn(['balance_before', 'balance_after', 'description', 'reference_id']);
            $table->dropColumn('transaction_type');
        });
        
        Schema::table('transaction_records', function (Blueprint $table) {
            $table->enum('transaction_type', ['deposit', 'withdrawal', 'trade_buy', 'trade_sell', 'admin_adjustment'])->after('amount');
        });
    }
};
