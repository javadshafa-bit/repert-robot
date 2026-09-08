<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * کدهای یک‌بارمصرف ورود به ربات.
 *
 * خودِ کد ذخیره نمی‌شود، فقط هشش — تا نشت دیتابیس به معنی امکان ورود نباشد.
 * attempts و expires_at جلوی حدس زدن را می‌گیرند و consumed_at جلوی
 * استفاده‌ی دوباره از یک کد را.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('representative_id')->constrained()->cascadeOnDelete();
            $table->string('chat_id');
            $table->string('phone_number', 20);
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'chat_id']);
            // برای سقف ارسال در ساعت: شمارش بر اساس شماره در بازه‌ی زمانی
            $table->index(['tenant_id', 'phone_number', 'created_at']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
