<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;

/**
 * نگه‌دارنده‌ی «مستأجر جاری» برای طول عمر یک درخواست (یا یک تکرار از حلقه‌ی artisan).
 *
 * دو نقطه این را پر می‌کنند:
 *  1) میدلور ResolveTenant  → از روی کاربر لاگین‌شده
 *  2) BotController::handle → از روی webhook_secret در مسیر وبهوک
 */
class TenantContext
{
    private static ?Tenant $tenant = null;

    /** اجازه‌ی موقت برای اجرای کوئری بدون محدودیت مستأجر (فقط برای اسکریپت‌های سطح پلتفرم) */
    private static bool $bypass = false;

    public static function set(?Tenant $tenant): void
    {
        self::$tenant = $tenant;
    }

    public static function get(): ?Tenant
    {
        return self::$tenant;
    }

    /**
     * شناسه‌ی مستأجر جاری.
     *
     * اگر هنوز صریحاً ست نشده باشد، از روی کاربر لاگین‌شده حدس زده می‌شود.
     * این fallback مهم است: route model binding (میدلور SubstituteBindings گروه web)
     * پیش از میدلور tenant اجرا می‌شود، و بدون آن رکورد سازمان دیگر در URL
     * قابل باز کردن بود (IDOR).
     */
    public static function id(): ?int
    {
        if (self::$tenant !== null) {
            return self::$tenant->id;
        }

        return Auth::hasUser() || Auth::check() ? Auth::user()?->tenant_id : null;
    }

    public static function check(): bool
    {
        return self::$tenant !== null;
    }

    public static function forget(): void
    {
        self::$tenant = null;
    }

    /** آیا الان اجازه‌ی عبور از global scope داده شده است؟ */
    public static function bypassed(): bool
    {
        return self::$bypass;
    }

    /**
     * اجرای یک closure بدون اعمال محدودیت مستأجر.
     * فقط جاهایی استفاده شود که آگاهانه به داده‌ی همه‌ی مستأجرها نیاز داریم
     * (مثلاً حلقه‌ی کرون پیام همگانی یا اسکریپت‌های مهاجرت).
     */
    public static function withoutScope(callable $callback)
    {
        $previous     = self::$bypass;
        self::$bypass = true;

        try {
            return $callback();
        } finally {
            self::$bypass = $previous;
        }
    }

    /** اجرای یک closure در بستر یک مستأجر مشخص و بازگرداندن وضعیت قبلی */
    public static function forTenant(Tenant $tenant, callable $callback)
    {
        $previous = self::$tenant;
        self::set($tenant);

        try {
            return $callback();
        } finally {
            self::set($previous);
        }
    }
}
