<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('category_fields', function (Blueprint $table) {
            // فیلدهایی که همیشه بعد از پاسخ فیلد والد نمایش داده می‌شوند (بدون شرط)
            // برخلاف parent_option_id که فقط بعد از انتخاب یک گزینه خاص فعال می‌شود
            $table->unsignedBigInteger('parent_field_id')->nullable()->after('parent_option_id');
        });
    }

    public function down(): void
    {
        Schema::table('category_fields', function (Blueprint $table) {
            $table->dropColumn('parent_field_id');
        });
    }
};
