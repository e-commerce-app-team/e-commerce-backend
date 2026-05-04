<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // المشتري (Buyer)
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // البائع (Seller) - سواء كان Vendor أو Wholesale
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');

            $table->decimal('total_price', 15, 2);

            // الحالات التي وضعتها أنت ممتازة
            $table->enum('status', ['pending', 'paid', 'failed_payment', 'delivered'])
                ->default('pending');

            $table->string('payment_method')->default('wallet');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
