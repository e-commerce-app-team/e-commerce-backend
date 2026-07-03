<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ads', function (Blueprint $table) {
            $table->id();

            // 🔥 من أنشأ الإعلان (التاجر)
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');

            // نوع الإعلان
            $table->enum('type', [
                'banner',              // بانر رئيسي
                'promoted_product',    // منتج معزز
                'featured_store',      // متجر مميز
                'paid_notification'    // إشعار مدفوع
            ])->default('banner');

            // تفاصيل الإعلان
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->string('link')->nullable();

            // مدة الإعلان
            $table->enum('duration', ['1_day', '3_days', '1_week', '1_month'])->default('1_day');
            $table->decimal('price', 10, 2); // السعر حسب المدة

            // تواريخ البداية والنهاية
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            // الحالة
            $table->enum('status', [
                'pending',      // قيد المراجعة
                'active',       // نشط
                'rejected',     // مرفوض
                'expired'       // منتهي
            ])->default('pending');

            // 🔥 إحصائيات الإعلان
            $table->integer('views_count')->default(0);
            $table->integer('clicks_count')->default(0);

            // ملاحظات الأدمن
            $table->text('admin_notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};