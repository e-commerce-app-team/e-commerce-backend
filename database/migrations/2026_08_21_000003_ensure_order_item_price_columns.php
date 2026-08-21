<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('order_items')) {
            return;
        }

        if (! Schema::hasColumn('order_items', 'unit_price')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->decimal('unit_price', 15, 2)->nullable()->after('quantity');
            });
        }

        if (! Schema::hasColumn('order_items', 'total_price')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->decimal('total_price', 15, 2)->nullable()->after('unit_price');
            });
        }
    }

    public function down(): void
    {
        // Keep order price data intact when rolling back.
    }
};
