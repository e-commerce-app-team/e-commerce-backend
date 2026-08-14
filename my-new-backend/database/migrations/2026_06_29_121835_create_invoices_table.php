<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // التاجر (Wholesale)
            $table->string('invoice_number')->unique(); // رقم الفاتورة المميز (مثال: INV-2026-0001)

            // المبالغ المالية والضرائب
            $table->decimal('subtotal', 10, 2);   // الإجمالي قبل الضريبة
            $table->decimal('vat_amount', 10, 2); // قيمة ضريبة القيمة المضافة (مثلاً 15%)
            $table->decimal('total', 10, 2);      // الإجمالي النهائي (Subtotal + VAT)

            $table->string('pdf_path')->nullable(); // مسار ملف الـ PDF المخزن على السيرفر
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};