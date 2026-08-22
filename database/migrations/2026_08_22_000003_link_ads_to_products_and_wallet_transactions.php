<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ads') && ! Schema::hasColumn('ads', 'product_id')) {
            Schema::table('ads', function (Blueprint $table): void {
                $table->foreignId('product_id')->nullable()->after('seller_id')
                    ->constrained('products')->nullOnDelete();
            });
        }

        if (Schema::hasTable('transactions') && ! Schema::hasColumn('transactions', 'ad_id')) {
            Schema::table('transactions', function (Blueprint $table): void {
                $table->foreignId('ad_id')->nullable()->after('order_id')
                    ->constrained('ads')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('transactions') && Schema::hasColumn('transactions', 'ad_id')) {
            Schema::table('transactions', function (Blueprint $table): void {
                $table->dropForeign(['ad_id']);
                $table->dropColumn('ad_id');
            });
        }

        if (Schema::hasTable('ads') && Schema::hasColumn('ads', 'product_id')) {
            Schema::table('ads', function (Blueprint $table): void {
                $table->dropForeign(['product_id']);
                $table->dropColumn('product_id');
            });
        }
    }
};
