<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * حساب سوپرادمین پلتفرم (مالک این نصب).
 * این حساب به هیچ سازمانی تعلق ندارد (tenant_id = null) و فقط به /platform دسترسی دارد.
 *
 * ایمیل/رمز از .env خوانده می‌شود:
 *   PLATFORM_ADMIN_EMAIL=...
 *   PLATFORM_ADMIN_PASSWORD=...
 */
class AdminSeeder extends Seeder {
    public function run(): void {
        $email    = env('PLATFORM_ADMIN_EMAIL', 'platform@example.com');
        $password = env('PLATFORM_ADMIN_PASSWORD');

        if (DB::table('users')->where('email', $email)->exists()) {
            $this->command?->warn("کاربر {$email} از قبل وجود دارد؛ چیزی ساخته نشد.");
            return;
        }

        if (!$password) {
            $this->command?->warn('PLATFORM_ADMIN_PASSWORD تنظیم نشده است؛ سوپرادمین پلتفرم ساخته نشد.');
            return;
        }

        DB::table('users')->insert([
            'tenant_id'         => null,
            'name'              => 'سوپرادمین پلتفرم',
            'email'             => $email,
            'password'          => Hash::make($password),
            'is_super_admin'    => false,
            'is_platform_admin' => true,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $this->command?->info("سوپرادمین پلتفرم ساخته شد: {$email}");
    }
}
