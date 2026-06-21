<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bot_states', function (Blueprint $table) {
            // صف فیلدهای باقیمانده برای پرسیدن (آرایه JSON از field_id ها)
            $table->json('field_queue')->nullable()->after('draft_data');
        });
    }

    public function down(): void
    {
        Schema::table('bot_states', function (Blueprint $table) {
            $table->dropColumn('field_queue');
        });
    }
};
