<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * محدوده‌ی مجاز برای فیلدهای نوع «تاریخ».
 *
 *   past   → فقط تا امروز (تاریخ تولد، تاریخ وفات، تاریخ برگزاری گذشته)
 *   future → فقط از امروز به بعد (تاریخ رویداد آینده، سررسید)
 *   any    → بدون محدودیت
 *
 * برای فیلدهایی که نوعشان date نیست بی‌اثر است.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('category_fields', function (Blueprint $table) {
            $table->string('date_range', 10)->default('any')->after('is_multiple');
        });
    }

    public function down(): void
    {
        Schema::table('category_fields', function (Blueprint $table) {
            $table->dropColumn('date_range');
        });
    }
};
