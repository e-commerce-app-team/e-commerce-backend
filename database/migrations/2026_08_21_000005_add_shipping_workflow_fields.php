<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            if (! Schema::hasColumn('orders', 'checkout_key')) {
                Schema::table('orders', function (Blueprint $table): void {
                    $table->string('checkout_key', 100)->nullable()->after('payment_method');
                    $table->index(['user_id', 'checkout_key']);
                });
            }
            if (! Schema::hasColumn('orders', 'shipping_pending')) {
                Schema::table('orders', function (Blueprint $table): void {
                    $table->boolean('shipping_pending')->default(false)->after('stock_reserved');
                });
            }
        }

        if (Schema::hasTable('sub_orders')) {
            if (! Schema::hasColumn('sub_orders', 'shipping_method')) {
                Schema::table('sub_orders', function (Blueprint $table): void {
                    $table->string('shipping_method', 40)->nullable()->after('status');
                });
            }
            if (! Schema::hasColumn('sub_orders', 'shipping_label')) {
                Schema::table('sub_orders', function (Blueprint $table): void {
                    $table->string('shipping_label')->nullable()->after('shipping_method');
                });
            }
            if (! Schema::hasColumn('sub_orders', 'shipping_cost')) {
                Schema::table('sub_orders', function (Blueprint $table): void {
                    $table->decimal('shipping_cost', 15, 2)->nullable()->after('shipping_label');
                });
            }
            if (! Schema::hasColumn('sub_orders', 'estimated_delivery')) {
                Schema::table('sub_orders', function (Blueprint $table): void {
                    $table->string('estimated_delivery')->nullable()->after('shipping_cost');
                });
            }
        }
    }

    public function down(): void
    {
        // Keep order and shipping snapshots intact when rolling back.
    }
};
