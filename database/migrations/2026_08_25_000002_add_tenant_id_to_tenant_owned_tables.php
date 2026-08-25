<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** جدول‌هایی که داده‌شان متعلق به یک مستأجر مشخص است */
    private array $tables = [
        'users', 'roles', 'provinces', 'representatives', 'departments',
        'department_fields', 'categories', 'category_fields', 'field_options',
        'reports', 'bot_states', 'monthly_statuses', 'broadcast_messages', 'settings',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                // nullable است چون: (۱) سوپرادمین پلتفرم به هیچ مستأجری تعلق ندارد
                // (۲) رکوردهای موجود در migration بعدی مقدار می‌گیرند.
                $t->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $t->index('tenant_id');
            });

            // SQLite اجازه‌ی افزودن foreign key به جدول موجود را نمی‌دهد؛
            // روی MySQL (production) کلید خارجی واقعی ساخته می‌شود.
            if (DB::getDriverName() !== 'sqlite') {
                Schema::table($table, function (Blueprint $t) {
                    $t->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
                });
            }
        }

        if (!Schema::hasColumn('users', 'is_platform_admin')) {
            Schema::table('users', function (Blueprint $t) {
                $t->boolean('is_platform_admin')->default(false)->after('is_super_admin');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                if (DB::getDriverName() !== 'sqlite') {
                    $t->dropForeign(['tenant_id']);
                }
                $t->dropIndex(['tenant_id']);
                $t->dropColumn('tenant_id');
            });
        }

        if (Schema::hasColumn('users', 'is_platform_admin')) {
            Schema::table('users', fn(Blueprint $t) => $t->dropColumn('is_platform_admin'));
        }
    }
};
