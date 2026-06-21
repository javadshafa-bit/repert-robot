<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('email');
        });

        // اولین کاربر موجود را سوپر ادمین می‌کنیم
        $firstId = DB::table('users')->min('id');
        if ($firstId) {
            DB::table('users')->where('id', $firstId)->update(['is_super_admin' => true]);
        }
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });
    }
};
