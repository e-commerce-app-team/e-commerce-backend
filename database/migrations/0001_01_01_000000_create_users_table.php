<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // معلومات أساسية (مشتركة للجميع)
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->string('password');
            $table->string('profile_photo')->nullable();

            // صور التحقق (حسب النوع)
            $table->string('id_card_photo')->nullable(); // للكل (حسب طلبك الأخير)
            $table->string('commercial_record_photo')->nullable(); // فقط للـ Wholesale
            $table->string('store_logo')->nullable(); // اختياري للبائعين

            // معلومات المتجر والأعمال
            $table->string('store_name')->nullable(); // اسم المتجر (للبائعين)
            $table->string('category')->nullable(); // التصنيف (للبائعين)
            $table->string('commercial_registration_number')->unique()->nullable(); // السجل (Wholesale)
            $table->string('tax_number')->nullable(); // الرقم الضريبي (Wholesale - اختياري)


            // الإعدادات والحالة
            $table->enum('role', ['buyer', 'vendor', 'wholesale'])->default('buyer');
            $table->enum('status', ['pending', 'approved', 'rejected', 'blocked'])->default('pending');

            // المحفظة والمالية
            $table->decimal('balance', 10, 2)->default(0);
            $table->string('payout_method')->nullable();
            $table->string('payout_account')->nullable();
            $table->string('wallet_pin')->nullable();

            $table->timestamp('phone_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

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
