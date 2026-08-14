<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            // 🔥 من أنشأ الكوبون (التاجر)
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');

            // معلومات الكوبون
            $table->string('code')->unique();
            $table->string('title')->nullable();
            $table->text('description')->nullable();

            // نوع وقيمة الخصم
            $table->enum('type', ['percentage', 'fixed', 'free_shipping'])->default('percentage');
            $table->decimal('value', 10, 2); // قيمة الخصم

            // شروط الاستخدام
            $table->decimal('min_order_amount', 10, 2)->nullable(); // الحد الأدنى للطلب
            $table->integer('max_uses')->nullable(); // عدد مرات الاستخدام الإجمالي
            $table->integer('used_count')->default(0); // كم مرة استُخدم
            $table->enum('usage_limit_per_user', ['unlimited', 'once'])->default('unlimited'); // لكل مستخدم

            // نطاق الصلاحية
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            // 🔥 المنتجات المشمولة بالكوبون
            $table->boolean('apply_to_all_products')->default(true); // كل المنتجات
            $table->json('product_ids')->nullable(); // أو منتجات محددة

            // الحالة
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};