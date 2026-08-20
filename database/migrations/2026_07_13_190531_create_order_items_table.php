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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_order_id')->constrained('sub_orders')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products');
            $table->unsignedBigInteger('variant_id')->nullable(); // هنا سنحفظ الـ variant للأبد
            $table->integer('quantity');

            // 🔥 أعمدة الأسعار
            $table->decimal('unit_price', 15, 2)->nullable(); // ✅ سعر الوحدة
            $table->decimal('total_price', 15, 2)->nullable(); // ✅ السعر الإجمالي (unit_price * quantity)

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};