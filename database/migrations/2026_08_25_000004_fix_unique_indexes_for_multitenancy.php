<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * یونیک‌های سراسری باید per-tenant شوند.
 *
 * نمونه: یک کاربر بله می‌تواند با ربات دو مستأجر مختلف صحبت کند و chat_id یکسانی داشته باشد؛
 * یا یک شماره تلفن در دو سازمان به‌عنوان دو نماینده‌ی جدا ثبت شود.
 */
return new class extends Migration {
    /** [جدول => [[ستون‌های یونیک قدیمی], ...]] */
    private array $map = [
        'settings'        => [['key']],
        'provinces'       => [['name']],
        'roles'           => [['name']],
        'bot_states'      => [['chat_id']],
        'representatives' => [['phone_number'], ['chat_id']],
    ];

    public function up(): void
    {
        foreach ($this->map as $table => $columnSets) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($columnSets as $columns) {
                Schema::table($table, function (Blueprint $t) use ($columns) {
                    $t->dropUnique($columns);
                });

                Schema::table($table, function (Blueprint $t) use ($columns) {
                    $t->unique(array_merge(['tenant_id'], $columns));
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->map as $table => $columnSets) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($columnSets as $columns) {
                Schema::table($table, function (Blueprint $t) use ($columns) {
                    $t->dropUnique(array_merge(['tenant_id'], $columns));
                });

                Schema::table($table, function (Blueprint $t) use ($columns) {
                    $t->unique($columns);
                });
            }
        }
    }
};
