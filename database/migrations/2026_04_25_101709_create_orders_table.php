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
            // 1. رقم الطلب (تلقائي الحفظ والزيادة المستمرة)
            $table->id();

            // معلومات وعلاقات المشتري والبائع (Foreign Keys)
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');   // المشتري (Buyer)
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade'); // البائع (Seller)

            // إجمالي السعر الخاص بالفاتورة
            $table->decimal('total_price', 15, 2);

            // 2. حالات الطلب مدمجة بالكامل (الحالات الخاصة بك + تبويبات لوحة التحكم)
            $table->enum('status', [
                'pending',             // جديد / معلق
                'paid',                // تم الدفع
                'failed_payment',      // فشل الدفع
                'processing',          // قيد التجهيز
                'shipped',             // تم الشحن / في الطريق
                'delivered',           // مكتمل / تم التسليم
                'cancelled_returned'   // ملغى / مرتجع
            ])->default('pending');

            // طريقة الدفع المستعملة
            $table->string('payment_method')->default('wallet');

            // 3. حالة الدفع والإدارة الآلية لنظام الضمان وحجز الأموال (Escrow)
            $table->enum('payment_status', [
                'escrow_locked',       // محجوز في الضمان
                'released',            // تم فك الحجز للبائع
                'refunded'             // تم الارتجاع للمشتري
            ])->default('escrow_locked');

            // 4. معلومات وعنوان التسليم التفصيلي للمشتري
            $table->string('shipping_address_title')->nullable(); // اسم العنوان (مثال: المنزل، العمل)
            $table->text('shipping_address_details');             // تفاصيل عنوان التسليم بالكامل

            // 5. ملاحظات وتعليمات خاصة قادمة من العميل للتاجر
            $table->text('customer_notes')->nullable();

            // 💡 التعديل الجديد: إضافة حقل تاريخ ووقت التجهيز/التسليم المتوقع 
            $table->dateTime('estimated_delivery_date')->nullable();

            // 6. الـ Timeline الزمني لحفظ تتبع مراحل الطلب (مصفوفة JSON)
            $table->json('status_timeline')->nullable();

            // 7. التاريخ والوقت (تنشأ وتتحدث تلقائياً عبر لارافيل لتسجيل وقت الطلب)
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