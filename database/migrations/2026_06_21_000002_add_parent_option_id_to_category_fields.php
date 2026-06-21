<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('category_fields', function (Blueprint $table) {
            // در SQLite نمی‌توان FK constraint به ALTER TABLE اضافه کرد
            // فقط ستون nullable integer اضافه می‌کنیم؛ relation در Model تعریف شده
            $table->unsignedBigInteger('parent_option_id')->nullable()->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('category_fields', function (Blueprint $table) {
            $table->dropColumn('parent_option_id');
        });
    }
};
