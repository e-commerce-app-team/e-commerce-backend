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

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->string('password');

            // Photos
            $table->string('profile_photo')->nullable();
            $table->string('id_card_photo')->nullable(); // خاص بالـ Vendor
            $table->string('commercial_record_photo')->nullable(); // خاص بالـ Wholesale

            // Business Info
            $table->string('display_name')->nullable(); // Vendor
            $table->string('company_name')->nullable(); // Wholesale
            $table->string('commercial_registration_number')->unique()->nullable(); // السجل التجاري
            $table->string('category')->nullable(); // أضيفي nullable() هنا            $table->integer('min_order_quantity')->nullable(); // الحد الأدنى للطلب
            $table->text('warehouse_address')->nullable(); // عنوان المستودع
            $table->integer('min_order_quantity')->nullable(); // تأكدي من وجود هذا السطر

            $table->enum('role', ['buyer', 'vendor', 'wholesale'])->default('buyer');
            $table->enum('status', ['pending', 'approved', 'rejected', 'blocked'])->default('pending');
            // Wallet Info
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
