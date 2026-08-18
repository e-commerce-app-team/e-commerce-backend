<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'fcm_token')) {
                $table->string('fcm_token')->nullable()->after('remember_token');
            }
            if (!Schema::hasColumn('users', 'shipping_settings')) {
                $table->json('shipping_settings')->nullable()->after('social_links');
            }
            if (!Schema::hasColumn('users', 'pickup_enabled')) {
                $table->boolean('pickup_enabled')->default(true)->after('shipping_settings');
            }
        });

        Schema::table('sub_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('sub_orders', 'shipping_method')) {
                $table->string('shipping_method')->nullable()->after('total');
            }
            if (!Schema::hasColumn('sub_orders', 'shipping_label')) {
                $table->string('shipping_label')->nullable()->after('shipping_method');
            }
            if (!Schema::hasColumn('sub_orders', 'shipping_cost')) {
                $table->decimal('shipping_cost', 10, 2)->default(0)->after('shipping_label');
            }
            if (!Schema::hasColumn('sub_orders', 'estimated_delivery')) {
                $table->string('estimated_delivery')->nullable()->after('shipping_cost');
            }
            if (!Schema::hasColumn('sub_orders', 'coupon_id')) {
                $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete()->after('estimated_delivery');
            }
            if (!Schema::hasColumn('sub_orders', 'discount_amount')) {
                $table->decimal('discount_amount', 10, 2)->default(0)->after('coupon_id');
            }
            if (!Schema::hasColumn('sub_orders', 'status')) {
                $table->string('status')->default('pending')->after('discount_amount');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'shipping_address_details')) {
                $table->text('shipping_address_details')->nullable()->change();
            }
            if (!Schema::hasColumn('orders', 'address_id')) {
                $table->foreignId('address_id')->nullable()->after('shipping_address_details');
            }
            if (!Schema::hasColumn('orders', 'shipping_lat')) {
                $table->decimal('shipping_lat', 10, 8)->nullable()->after('address_id');
            }
            if (!Schema::hasColumn('orders', 'shipping_lng')) {
                $table->decimal('shipping_lng', 11, 8)->nullable()->after('shipping_lat');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['address_id', 'shipping_lat', 'shipping_lng']);
        });

        Schema::table('sub_orders', function (Blueprint $table) {
            $table->dropForeign(['coupon_id']);
            $table->dropColumn([
                'shipping_method', 'shipping_label', 'shipping_cost',
                'estimated_delivery', 'coupon_id', 'discount_amount', 'status',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['fcm_token', 'shipping_settings', 'pickup_enabled']);
        });
    }
};
