<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // 🔥 تعديل: جعل order_id يقبل NULL
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('cascade');
            
            $table->decimal('discount_amount', 10, 2);
            $table->decimal('order_total_before_discount', 10, 2);
            $table->decimal('order_total_after_discount', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
    }
};