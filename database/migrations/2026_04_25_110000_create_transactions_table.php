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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // ربط العملية بالمستخدم (مشتري أو بائع)
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');            // نوع العملية: شحن، دفع، أو استرداد
            // استخدمنا enum لضمان عدم إدخال قيم عشوائية
            $table->enum('type', ['deposit', 'payment', 'refund', 'withdrawal']); // أضفنا withdrawal هنا
            // المبلغ (دائماً نستخدم decimal للدقة المالية)
            $table->decimal('amount', 15, 2);

            // وصف بسيط للعملية (مثلاً: Order #102 Payment)
            $table->string('description')->nullable();

            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
