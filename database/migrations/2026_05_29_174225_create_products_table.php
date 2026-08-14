<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // العلاقات (البائع والتصنيف)
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');

            // البيانات الأساسية والوسائط
            $table->string('name');
            $table->text('description');
            $table->json('images'); // مصفوفة الصور (حتى 10 صور)
            $table->string('video_url')->nullable(); // فيديو اختياري

            // الأسعار (العادي، الجملة، والعروض)
            $table->decimal('original_price', 15, 2);
            $table->decimal('wholesale_price', 15, 2)->nullable(); // حقل الجملة
            $table->decimal('offer_price', 15, 2)->nullable();
            $table->timestamp('offer_expires_at')->nullable(); // تاريخ انتهاء العرض

            // الرموز والكميات والمستودعات
            $table->string('sku')->unique();
            $table->integer('quantity')->default(0);
            $table->integer('min_wholesale_qty')->nullable(); // الحد الأدنى للجملة
            $table->json('warehouse_stock')->nullable();      // توزيع الكميات على الفروع
            $table->integer('alert_threshold')->default(5);   // حد التنبيه للنقص

            // الأبعاد والوزن لحساب تكاليف الشحن
            $table->decimal('weight', 8, 2)->nullable();
            $table->decimal('length', 8, 2)->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();

            // الحالة وإعدادات الشحن
            $table->enum('status', ['active', 'draft', 'hidden'])->default('draft');
            $table->unsignedInteger('views')->default(0);
            $table->boolean('is_free_shipping')->default(false); // الشحن المجاني

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');

    }

};