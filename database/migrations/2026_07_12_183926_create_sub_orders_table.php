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
        // جدول لربط كل طلبية بتاجر معين عند التسوق بالسلة من كزا متجر
        Schema::create('sub_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('seller_id')->constrained('users');

            // 🔥 أعمدة السعر
            $table->decimal('total', 10, 2);           // ✅ المجموع الكلي
            $table->decimal('total_price', 15, 2)->nullable(); // ✅ المجموع الكلي (للتوافق)

            $table->enum('status', ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sub_orders');
    }
};