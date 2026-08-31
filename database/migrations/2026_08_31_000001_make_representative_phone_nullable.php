<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * شماره تماس نماینده اختیاری می‌شود.
 *
 * سازمان‌هایی که نمی‌خواهند هنگام ثبت نماینده شماره وارد کنند، با تنظیم
 * require_representative_phone می‌توانند این فیلد را خالی بگذارند.
 * چنین نماینده‌ای فقط رکورد آماری است و تا زمانی که شماره‌اش ثبت نشود
 * نمی‌تواند در ربات احراز هویت کند.
 *
 * توجه: یونیک (tenant_id, phone_number) باقی می‌ماند؛ در MySQL و SQLite
 * چند مقدار NULL با یونیک تداخل ندارند.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('representatives', function (Blueprint $table) {
            $table->string('phone_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        // رکوردهای بدون شماره را با یک مقدار یکتای موقت پر می‌کنیم تا برگشت ممکن باشد
        \Illuminate\Support\Facades\DB::table('representatives')
            ->whereNull('phone_number')
            ->orderBy('id')
            ->each(function ($row) {
                \Illuminate\Support\Facades\DB::table('representatives')
                    ->where('id', $row->id)
                    ->update(['phone_number' => 'no-phone-' . $row->id]);
            });

        Schema::table('representatives', function (Blueprint $table) {
            $table->string('phone_number')->nullable(false)->change();
        });
    }
};
