<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->json('dept_data')->nullable();
        });

        Schema::table('bot_states', function (Blueprint $table) {
            $table->json('dept_draft_data')->nullable();
            $table->integer('current_dept_field_index')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('dept_data');
        });

        Schema::table('bot_states', function (Blueprint $table) {
            $table->dropColumn(['dept_draft_data', 'current_dept_field_index']);
        });
    }
};
