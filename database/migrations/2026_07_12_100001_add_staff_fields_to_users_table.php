<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add permissions (JSON) and seller_id columns to users table
     * so that a staff member carries their permission set and knows
     * which seller they belong to.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Already have 'role', just need these two new columns
            $table->json('permissions')->nullable()->after('role');
            $table->foreignId('seller_id')
                  ->nullable()
                  ->after('permissions')
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
            $table->dropColumn(['permissions', 'seller_id']);
        });
    }
};
