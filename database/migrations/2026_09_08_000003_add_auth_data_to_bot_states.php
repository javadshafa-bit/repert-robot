<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حافظه‌ی موقت جریان ورود (کد پرسنلی و شماره‌ی در حال وارد شدن).
 *
 * draft_data مال پیش‌نویس گزارش است و نباید با داده‌ی احراز هویت قاطی شود؛
 * جریان ورود پیش از انتخاب دسته‌بندی اتفاق می‌افتد و عمر جداگانه‌ای دارد.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('bot_states', function (Blueprint $table) {
            $table->json('auth_data')->nullable()->after('representative_id');
        });
    }

    public function down(): void
    {
        Schema::table('bot_states', function (Blueprint $table) {
            $table->dropColumn('auth_data');
        });
    }
};
