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
        Schema::create('payout_requests', function (Blueprint $table) {
            $table->id();
            // ربط الطلب بالمستخدم (البائع)
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->decimal('amount', 15, 2); // المبلغ المطلوب سحبه

            // جلب طريقة الحساب وقت الطلب لضمان التوثيق حتى لو غير المستخدم إعداداته لاحقاً
            $table->string('payout_method');  // wallet أو cash
            $table->string('payout_account'); // رقم الموبايل أو كلمة Manual

            // حالة الطلب
            $table->enum('status', ['pending', 'completed', 'rejected'])->default('pending');

            // حقل اختياري لإضافة سبب الرفض أو ملاحظات من الأدمن
            $table->text('admin_notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_requests');
    }
};
