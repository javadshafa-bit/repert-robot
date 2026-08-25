<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\BotConnector;
use Illuminate\Console\Command;

/**
 * ثبت دوباره‌ی وبهوک روی بله با آدرس جدیدِ per-tenant.
 *
 *   php artisan tenants:refresh-webhook            → همه‌ی سازمان‌های تاییدشده‌ی دارای توکن
 *   php artisan tenants:refresh-webhook 1          → فقط سازمان شماره ۱
 *
 * بعد از مهاجرت به مسیر /api/bot/webhook/{secret} حتماً یک‌بار اجرا شود،
 * وگرنه ربات‌های فعلی که روی مسیر قدیمی ثبت شده‌اند پیام دریافت نمی‌کنند.
 */
class RefreshTenantWebhook extends Command
{
    protected $signature   = 'tenants:refresh-webhook {tenant? : شناسه سازمان}';
    protected $description = 'ثبت دوباره‌ی وبهوک بله با آدرس اختصاصی هر سازمان';

    public function handle(BotConnector $bot): int
    {
        // در محیط کنسول، route() آدرس را از APP_URL می‌سازد نه از هاست درخواست.
        // اگر APP_URL اشتباه باشد، setWebhook یک آدرس غیرقابل‌دسترس ثبت می‌کند،
        // بله «ok» برمی‌گرداند و ربات بی‌سروصدا می‌میرد. پس همین‌جا متوقف می‌شویم.
        $appUrl = (string) config('app.url');
        $this->line("APP_URL = {$appUrl}");

        if (!$this->appUrlIsUsable($appUrl)) {
            $this->error('APP_URL برای ثبت وبهوک معتبر نیست. باید یک آدرس عمومی https باشد (نه localhost).');
            $this->error('اول در .env سرور مقدار APP_URL را درست کن، بعد `php artisan config:clear` بزن و دوباره اجرا کن.');

            return self::FAILURE;
        }

        $query = Tenant::query()->whereNotNull('bot_token')->where('status', Tenant::STATUS_ACTIVE);

        if ($id = $this->argument('tenant')) {
            $query->whereKey($id);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->warn('سازمانی با توکن ربات و وضعیت تاییدشده پیدا نشد.');
            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($tenants as $tenant) {
            $tenant->ensureWebhookSecret();
            $result = $bot->setWebhook($tenant);

            if (!$result['ok']) {
                $failed++;
                $this->error("tenant#{$tenant->id} ({$tenant->name}) → {$result['message']}");
                continue;
            }

            $this->info("tenant#{$tenant->id} ({$tenant->name}) → {$result['url']}");

            // چیزی که ما فرستادیم لزوماً چیزی نیست که روی بله نشسته؛ خودش را بپرس و نشان بده.
            $registered = $bot->registeredWebhookUrl($tenant);

            if ($registered === null) {
                $this->warn('   getWebhookInfo پاسخ نداد — آدرس ثبت‌شده روی بله قابل تایید نیست.');
            } elseif ($registered === $result['url']) {
                $this->line('   تایید شد: همین آدرس روی بله ثبت است.');
            } else {
                $failed++;
                $this->error("   ناهماهنگ! آدرس ثبت‌شده روی بله: " . ($registered ?: '(خالی)'));
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** آدرس عمومی https و غیر لوکال باشد */
    private function appUrlIsUsable(string $url): bool
    {
        if (!str_starts_with($url, 'https://')) {
            return false;
        }

        foreach (['localhost', '127.0.0.1', '::1', '0.0.0.0'] as $needle) {
            if (str_contains($url, $needle)) {
                return false;
            }
        }

        return true;
    }
}
