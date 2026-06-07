<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // البائع
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade'); // التصنيف

            // الاسم والوصف والوسائط
            $table->string('name');
            $table->text('description');
            $table->json('images'); // مصفوفة الصور مدمجة هنا (حتى 10 صور)
            $table->string('video_url')->nullable(); // فيديو اختياري

            // الأسعار والكميات
            $table->decimal('original_price', 15, 2);
            $table->decimal('offer_price', 15, 2)->nullable();
            $table->timestamp('offer_expires_at')->nullable(); // تاريخ انتهاء العرض

            $table->string('sku')->unique();
            $table->integer('quantity')->default(0);
            $table->integer('alert_threshold')->default(5); // حد التنبيه

            // الأبعاد والوزن لحساب الشحن
            $table->decimal('weight', 8, 2)->nullable();
            $table->decimal('length', 8, 2)->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();

            // الحالة
            $table->enum('status', ['active', 'draft', 'hidden'])->default('draft');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
    
};