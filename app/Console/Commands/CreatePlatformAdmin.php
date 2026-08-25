<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * ساخت (یا ارتقای) حساب سوپرادمین پلتفرم.
 *
 *   php artisan tenants:create-platform-admin owner@example.com --name="سوپرادمین" --password=...
 *
 * اگر ایمیل از قبل وجود داشته باشد، همان حساب به سوپرادمین پلتفرم ارتقا می‌یابد
 * (tenant_id = null می‌شود، یعنی دیگر به هیچ سازمانی تعلق ندارد).
 */
class CreatePlatformAdmin extends Command
{
    protected $signature = 'tenants:create-platform-admin
                            {email : ایمیل حساب سوپرادمین پلتفرم}
                            {--name= : نام نمایشی}
                            {--password= : رمز عبور (اگر ندهید پرسیده می‌شود)}';

    protected $description = 'ساخت یا ارتقای حساب سوپرادمین پلتفرم (بدون تعلق به هیچ سازمانی)';

    public function handle(): int
    {
        $email = trim($this->argument('email'));
        $user  = User::where('email', $email)->first();

        if ($user) {
            if (!$this->confirm("کاربر «{$user->name}» با این ایمیل وجود دارد. به سوپرادمین پلتفرم ارتقا یابد؟ (از سازمان فعلی جدا می‌شود)", false)) {
                return self::SUCCESS;
            }

            $user->forceFill([
                'tenant_id'         => null,
                'is_platform_admin' => true,
                'is_super_admin'    => false,
            ])->save();

            $this->info("حساب {$email} به سوپرادمین پلتفرم ارتقا یافت.");
            return self::SUCCESS;
        }

        $password = $this->option('password') ?: $this->secret('رمز عبور جدید (حداقل ۸ کاراکتر)');

        if (strlen((string) $password) < 8) {
            $this->error('رمز عبور باید حداقل ۸ کاراکتر باشد.');
            return self::FAILURE;
        }

        $user = User::create([
            'tenant_id'         => null,
            'name'              => $this->option('name') ?: 'سوپرادمین پلتفرم',
            'email'             => $email,
            'password'          => Hash::make($password),
            'is_super_admin'    => false,
            'is_platform_admin' => true,
        ]);

        $this->info("سوپرادمین پلتفرم ساخته شد: {$user->email}");

        return self::SUCCESS;
    }
}
