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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->unique();

            // 🔥🔥🔥 أعمدة الضريبة (الجديدة) 🔥🔥🔥
            $table->decimal('tax_rate', 5, 2)->default(0);    // نسبة الضريبة
            $table->string('tax_label')->nullable();// تسمية الضريبة

            $table->string('image_url')->nullable();
            $table->string('icon_url')->nullable();
            $table->integer('order_position')->default(0); // الترتيب للسحب والإفلات
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};