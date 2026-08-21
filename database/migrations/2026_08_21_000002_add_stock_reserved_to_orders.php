<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'stock_reserved')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->boolean('stock_reserved')->default(false)->after('payment_status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'stock_reserved')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('stock_reserved');
            });
        }
    }
};
