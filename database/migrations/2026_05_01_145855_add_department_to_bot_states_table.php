<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bot_states', function (Blueprint $table) {
            $table->string('department_id')->nullable()->after('representative_id');
        });
    }

    public function down(): void
    {
        Schema::table('bot_states', function (Blueprint $table) {
            $table->dropColumn('department_id');
        });
    }
};