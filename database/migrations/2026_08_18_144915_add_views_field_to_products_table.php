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
        // ✅ تحقق من وجود العمود قبل إضافته
        if (!Schema::hasColumn('products', 'views')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedBigInteger('views')->default(0)->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ✅ تحقق من وجود العمود قبل حذفه
        if (Schema::hasColumn('products', 'views')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('views');
            });
        }
    }
};