<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** A quote is intentionally unknown until the seller accepts the request. */
    public function up(): void
    {
        if (! Schema::hasTable('sub_orders')) {
            return;
        }

        Schema::table('sub_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('sub_orders', 'shipping_cost')) {
                $table->decimal('shipping_cost', 15, 2)->nullable()->change();
            }

            if (Schema::hasColumn('sub_orders', 'estimated_delivery')) {
                $table->string('estimated_delivery')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        // A pending order must keep its nullable shipping quote on rollback.
    }
};
