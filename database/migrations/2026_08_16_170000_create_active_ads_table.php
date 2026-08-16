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
        Schema::create('active_ads', function (Blueprint $table) {
          $table->id();
        $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
        $table->string('image');
        $table->string('link_type'); // e.g., 'store', 'product', 'offer'
        $table->unsignedBigInteger('link_id');
        $table->integer('position')->default(0); // الأولوية/الترتيب
        $table->date('start_date');
        $table->date('end_date');
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('active_ads');
    }
};
