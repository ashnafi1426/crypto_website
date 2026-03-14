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
        Schema::table('users', function (Blueprint $table) {
            // Check if columns don't exist before adding
            if (!Schema::hasColumn('users', 'password_reset_token')) {
                $table->string('password_reset_token')->nullable()->after('email_verification_token');
            }
            if (!Schema::hasColumn('users', 'password_reset_expires_at')) {
                $table->timestamp('password_reset_expires_at')->nullable()->after('password_reset_token');
            }
            
            // Make password nullable for social login
            $table->string('password')->nullable()->change();
            
            // Add indexes for performance (only if they don't exist)
            if (!$this->indexExists('users', ['email', 'provider'])) {
                $table->index(['email', 'provider']);
            }
            if (!$this->indexExists('users', ['provider', 'provider_id'])) {
                $table->index(['provider', 'provider_id']);
            }
            if (!$this->indexExists('users', ['email_verification_token'])) {
                $table->index('email_verification_token');
            }
            if (!$this->indexExists('users', ['password_reset_token'])) {
                $table->index('password_reset_token');
            }
        });
    }
    
    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, array $columns): bool
    {
        $indexes = Schema::getIndexes($table);
        $columnString = implode('_', $columns);
        
        foreach ($indexes as $index) {
            if ($index['columns'] === $columns) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email', 'provider']);
            $table->dropIndex(['provider', 'provider_id']);
            $table->dropIndex(['email_verification_token']);
            $table->dropIndex(['password_reset_token']);
            
            $table->dropColumn([
                'provider',
                'provider_id', 
                'avatar',
                'email_verification_token',
                'password_reset_token',
                'password_reset_expires_at'
            ]);
            
            $table->string('password')->nullable(false)->change();
        });
    }
};