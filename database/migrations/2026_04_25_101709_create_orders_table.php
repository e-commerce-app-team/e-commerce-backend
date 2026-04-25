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

            // المزود (Vendor أو Wholesale)
            $table->foreignId('vendor_id')->constrained('users')->onDelete('cascade');

            // السعر الإجمالي للطلب (قبل خصم العمولة)
            $table->decimal('total_price', 15, 2);

            // حالة الطلب (مهم جداً للتابع تبعنا)
            // الحالات المقترحة: pending, processing, shipped, delivered, cancelled
            $table->enum('status', ['pending', 'paid', 'failed_payment', 'delivered'])
                ->default('pending');
            // اختياري: حالة الدفع (cash_on_delivery, wallet, etc.)
            $table->string('payment_method')->nullable();

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
