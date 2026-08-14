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
        Schema::create('order_items', function (Blueprint $table) {
          $table->id();
        $table->foreignId('sub_order_id')->constrained('sub_orders')->onDelete('cascade');
        $table->foreignId('product_id')->constrained('products');
        $table->unsignedBigInteger('variant_id')->nullable(); // هنا سنحفظ الـ variant للأبد
        $table->integer('quantity');
        $table->decimal('price', 10, 2); // السعر وقت الشراء
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
