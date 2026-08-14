<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // 1. معلومات الحساب الشخصية (مشتركة)
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->string('password');
            $table->string('profile_photo')->nullable(); // صورة الملف الشخصي

            // 2. صور التحقق والهوية
            $table->string('id_card_photo')->nullable();
            $table->string('commercial_record_photo')->nullable(); // (Wholesale)

            // 3. معلومات المتجر الأساسية والشعار والغلاف
            $table->string('store_name')->nullable();
            $table->text('store_description')->nullable(); // وصف المتجر
            $table->string('store_logo')->nullable();      // الشعار (Logo)
            $table->string('store_cover_photo')->nullable(); // غلاف المتجر

            $table->string('category')->nullable(); // التصنيف (للبائعين)

            // 4. أوقات الدوام والعطلات وسياسة الإرجاع
            $table->json('working_hours')->nullable();     // أوقات الدوام وأيام العطل
            $table->text('return_policy')->nullable();     // سياسة الإرجاع

            // 5. معلومات التواصل الإضافية للمتجر وروابط السوشيال
            $table->string('store_email')->nullable();     // بريد التواصل للمتجر
            $table->json('social_links')->nullable();      // روابط حسابات التواصل الاجتماعي

            // 6. الموقع الجغرافي والعنوان التفصيلي
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->text('detailed_address')->nullable();  // العنوان التفصيلي

            // 7. معلومات الأعمال والأرقام الرسمية (Wholesale)
            $table->string('commercial_registration_number')->unique()->nullable();
            $table->string('tax_number')->nullable();

            // 8. الإعدادات والمالية
            $table->enum('role', ['buyer', 'vendor', 'wholesale'])->default('buyer');
            $table->enum('status', ['pending', 'approved', 'rejected', 'blocked'])->default('pending');
            $table->decimal('balance', 10, 2)->default(0);
            $table->string('payout_method')->nullable();
            $table->string('payout_account')->nullable();
            $table->string('wallet_pin')->nullable();

            $table->timestamp('phone_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // جدول الجلسات كما هو
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
    }
};