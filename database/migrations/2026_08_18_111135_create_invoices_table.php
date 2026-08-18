<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // العلاقات
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');

            // رقم الفاتورة
            $table->string('invoice_number')->unique();

            // نوع الفاتورة: 'order' (wholesale فقط) | 'commission' (للجميع)
            $table->enum('type', ['order', 'commission'])->default('order');

            // المبالغ
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('commission_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2);

            // معلومات البائع
            $table->string('seller_name')->nullable();
            $table->string('seller_tax_number')->nullable();
            $table->string('seller_cr')->nullable(); // Commercial Register

            // تفاصيل الفاتورة (JSON)
            $table->json('line_items')->nullable();

            // حالة الفاتورة
            $table->enum('status', ['issued', 'cancelled', 'refunded'])->default('issued');

            // ملاحظات
            $table->text('notes')->nullable();

            // رابط ملف PDF
            $table->string('pdf_path')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};