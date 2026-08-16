<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('store_reviews', function (Blueprint $table) {
         $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); 
            $table->foreignId('store_id')->constrained('users')->onDelete('cascade'); 
            $table->tinyInteger('rating'); // من 1 إلى 5
            $table->text('comment')->nullable();
            $table->timestamps();
            // ضمان عدم قيام نفس المشتري بتقييم نفس المتجر أكثر من مرة
            $table->unique(['user_id', 'store_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_reviews');
    }
};
