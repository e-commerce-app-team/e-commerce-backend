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
        Schema::create('order_product', function (Blueprint $table) {
            $table->id();

            // ربط معرف الطلب (مع الحذف التلقائي إذا حُذف الطلب الأصلي)
            $table->foreignId('order_id')->constrained()->onDelete('cascade');

            // ربط معرف المنتج (مع الحذف التلقائي إذا حُذف المنتج الأصلي)
            $table->foreignId('product_id')->constrained()->onDelete('cascade');

            // حقول الكمية والسعر وقت الشراء التي طلبناها في الـ Pivot
            $table->integer('quantity');
            $table->decimal('price', 10, 2);

            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_product');
    }
};
