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
        Schema::create('departments', function (Blueprint $table) {
            $table->id();

            // ربط القسم بالبائع (التاجر أو تاجر الجملة) الذي قام بإنشائه
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('departments')->onDelete('cascade');

            $table->string('name'); // اسم القسم (مثل: ملابس صيفية)
            $table->string('slug')->unique();
            $table->string('image_url')->nullable();
            $table->string('icon_url')->nullable();

            // ميزات إضافية مثل الترتيب والظهور لمتجر التاجر
            $table->integer('order_position')->default(0);
            $table->boolean('is_visible')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};