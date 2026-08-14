<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Modify the role enum to include 'staff'
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('buyer', 'vendor', 'wholesale', 'staff') DEFAULT 'buyer'");
        
        // Make the phone column nullable since staff members don't need it initially
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Cannot easily revert enum, but we revert phone nullable
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable(false)->change();
        });
    }
};
