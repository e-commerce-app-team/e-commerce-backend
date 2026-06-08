<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->json('attributes');
            $table->string('sku')->unique()->nullable(); // SKU خاص بهذا المتغير تحديداً
            $table->decimal('price', 15, 2)->nullable(); // سعر مستقل (إذا تُرِك فارغاً يأخذ سعر المنتج الأساسي)
            $table->integer('quantity')->default(0);    // كمية المخزون الخاصة بهذا اللون والمقاس
            $table->string('image_url')->nullable();    // صورة مستقلة خاصة بهذا المتغير فقط
            $table->boolean('is_active')->default(true); // تفعيل / إيقاف المتغير دون حذفه
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
