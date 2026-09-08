<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * کد پرسنلی نماینده — پایه‌ی ورود با کد پرسنلی + شماره + OTP.
 *
 * یونیک per-tenant است، نه سراسری: دو سازمان می‌توانند کد پرسنلی یکسان
 * داشته باشند. NULL هم مجاز است و MySQL چند NULL را در یونیک می‌پذیرد،
 * پس نماینده‌های قدیمی بدون کد دست‌نخورده می‌مانند.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('representatives', function (Blueprint $table) {
            $table->string('personnel_code', 50)->nullable()->after('last_name');
            $table->unique(['tenant_id', 'personnel_code']);
        });
    }

    public function down(): void
    {
        Schema::table('representatives', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'personnel_code']);
            $table->dropColumn('personnel_code');
        });
    }
};
