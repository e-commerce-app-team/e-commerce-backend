<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('sub_orders')) {
            return;
        }

        Schema::table('sub_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('sub_orders', 'shipping_approved')) {
                $table->boolean('shipping_approved')->default(false)->after('shipping_cost');
            }
            if (! Schema::hasColumn('sub_orders', 'shipping_approved_at')) {
                $table->timestamp('shipping_approved_at')->nullable()->after('shipping_approved');
            }
            if (! Schema::hasColumn('sub_orders', 'shipment_state')) {
                $table->string('shipment_state', 30)->default('pending')->after('status');
            }
            if (! Schema::hasColumn('sub_orders', 'escrow_release_at')) {
                $table->timestamp('escrow_release_at')->nullable()->after('escrow_amount');
            }
            if (! Schema::hasColumn('sub_orders', 'escrow_released_at')) {
                $table->timestamp('escrow_released_at')->nullable()->after('escrow_release_at');
            }
            if (! Schema::hasColumn('sub_orders', 'delivery_confirmed_at')) {
                $table->timestamp('delivery_confirmed_at')->nullable()->after('escrow_released_at');
            }
            if (! Schema::hasColumn('sub_orders', 'delivery_confirmation_type')) {
                $table->string('delivery_confirmation_type', 30)->nullable()->after('delivery_confirmed_at');
            }
            if (! Schema::hasColumn('sub_orders', 'auto_release_days')) {
                $table->unsignedInteger('auto_release_days')->nullable()->after('delivery_confirmation_type');
            }
        });

        DB::table('sub_orders')
            ->whereNotNull('shipping_cost')
            ->where('shipping_cost', 0)
            ->where('shipping_approved', false)
            ->update(['shipping_approved' => true, 'shipping_approved_at' => now()]);

        if (Schema::hasTable('platform_settings')) {
            \App\Models\PlatformSetting::updateOrCreate(
                ['key' => 'auto_release_days'],
                [
                    'value' => '3',
                    'type' => 'integer',
                    'label' => 'Automatic escrow release period in days',
                    'description' => 'Days after shipment before eligible unpaid delivery is auto-confirmed.',
                ],
            );
        }
    }

    public function down(): void
    {
        // Workflow snapshots are intentionally retained on rollback.
    }
};
