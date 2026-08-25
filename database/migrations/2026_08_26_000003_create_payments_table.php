<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * پرداخت‌ها — مالکیت با tenant_id مشخص می‌شود (مدل Payment از BelongsToTenant استفاده می‌کند).
 * همه‌ی مبالغ به **تومان** ذخیره می‌شوند؛ تبدیل به ریال فقط لحظه‌ی ارسال به زرین‌پال انجام می‌شود.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();        // چه کسی پرداخت را شروع کرد
            $table->unsignedBigInteger('amount');                     // مبلغ نهایی پس از تخفیف (تومان)
            $table->unsignedBigInteger('original_amount');            // مبلغ قبل از تخفیف
            $table->unsignedBigInteger('discount_code_id')->nullable();
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedInteger('days_granted');                  // چند روز بابت این پرداخت داده می‌شود
            $table->string('gateway')->default('zarinpal');

            // یکتا بودن authority تضمین دیتابیسی است برای idempotent بودن callback
            $table->string('authority')->nullable()->unique();
            $table->string('ref_id')->nullable();                     // کد رهگیری نهایی پس از verify

            $table->string('status')->default('pending');             // pending|paid|failed|canceled
            $table->timestamp('paid_at')->nullable();
            $table->json('raw_response')->nullable();                 // پاسخ خام درگاه (بدون merchant_id)
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('status');
        });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('payments', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('discount_code_id')->references('id')->on('discount_codes')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
