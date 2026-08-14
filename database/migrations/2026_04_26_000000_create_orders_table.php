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
            // $table->foreignId('seller_id')->constrained('users')->onDelete('cascade'); // البائع (Seller)
            $table->unsignedBigInteger('seller_id')->nullable()->change();
            // إجمالي السعر الخاص بالفاتورة
            $table->decimal('total_price', 15, 2);

            // 2. حالات الطلب مدمجة بالكامل حسب التدفق المعتمد
            $table->enum('status', [
                'pending',             // جديد / معلق / بانتظار الدفع أو موافقة التاجر
                'paid',                // تم الدفع (للتوافق القديم)
                'failed_payment',      // فشل الدفع
                'processing',          // قيد التجهيز (وافق عليه التاجر)
                'shipped',             // تم الشحن / في الطريق
                'delivered',           // مكتمل / تم التسليم (وتحرير الأموال)
                'cancelled_returned'   // ملغى / مرتجع
            ])->default('pending');

            // طريقة الدفع المستعملة
            $table->string('payment_method')->default('wallet');

            // 3. حالة الدفع والإدارة الآلية لنظام الضمان وحجز الأموال (Escrow) 🌟
            $table->enum('payment_status', [
                'unpaid',              // غير مدفوع (عند الإنشاء فوراً)
                'paid_escrow',         // مدفوع ومحجوز في الضمان (بعد دفع المشتري وقبل تحرير المال)
                'released',            // تم فك الحجز وترحيله لرصيد البائع المتاح (بعد التأكيد أو الـ 48 ساعة)
                'refunded'             // تم الارتجاع للمشتري
            ])->default('unpaid');

            // 4. معلومات وعنوان التسليم التفصيلي للمشتري
            $table->string('shipping_address_title')->nullable(); // اسم العنوان (مثال: المنزل، العمل)
            $table->text('shipping_address_details');             // تفاصيل عنوان التسليم بالكامل

            // 5. ملاحظات وتعليمات خاصة قادمة من العميل للتاجر
            $table->text('customer_notes')->nullable();

            // 💡 التعديلات الجديدة: التوثيق الدقيق للوقت لإدارة الـ 48 ساعة تلقائياً 🌟
            $table->timestamp('shipped_at')->nullable();          // وقت الشحن الفعلي (يبدأ منه مؤقت الـ 48 ساعة)
            $table->timestamp('delivered_at')->nullable();        // وقت الاستلام الفعلي (سواء ضغط زر المشتري أو تلقائي)
            $table->dateTime('estimated_delivery_date')->nullable(); // وقت التسليم المتوقع

            // 🔥 حقول الكوبونات (المضافة حديثاً)
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->decimal('discount_amount', 10, 2)->default(0);

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
