<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * مستأجر جاری را از روی کاربر لاگین‌شده مشخص می‌کند.
 * بدون این، مدل‌های دارای BelongsToTenant در محیط وب هیچ رکوردی برنمی‌گردانند.
 *
 * گیتِ اشتراک هم اینجاست، نه روی لاگین: کاربری که پرداخت نکرده یا اشتراکش تمام شده
 * **باید بتواند وارد شود**، وگرنه هیچ‌وقت به صفحه‌ی پرداخت نمی‌رسد. فقط سازمان
 * معلق‌شده (تصمیم دستی سوپرادمین) از در ورودی برگردانده می‌شود.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next)
    {
        // TenantContext حالت static دارد. با php-fpm هر درخواست پروسه‌ی تازه است و
        // مشکلی نیست، ولی زیر Octane یا یک queue worker واقعی، مستأجرِ درخواست قبلی
        // باقی می‌ماند و داده‌ی سازمان A به سازمان B نشان داده می‌شود. بیمه‌ی ارزان:
        TenantContext::forget();

        $user = Auth::user();

        // سوپرادمین پلتفرم به هیچ مستأجری تعلق ندارد و نباید به پنل سازمانی برود
        if ($user->isPlatformAdmin()) {
            return redirect()->route('platform.dashboard');
        }

        $tenant = $user->tenant;

        if (!$tenant || $tenant->status === Tenant::STATUS_SUSPENDED) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => $this->messageFor($tenant),
            ]);
        }

        TenantContext::set($tenant);

        // هم‌گام‌سازی تنبل: ممکن است اشتراک بین دو اجرای کرون تمام شده باشد.
        $tenant->refreshStatus();

        if (!$tenant->hasActiveSubscription() && !$request->routeIs('billing.*')) {
            return redirect()->route('billing.index');
        }

        return $next($request);
    }

    private function messageFor(?Tenant $tenant): string
    {
        if ($tenant?->status === Tenant::STATUS_SUSPENDED) {
            return 'حساب سازمان شما توسط پشتیبانی معلق شده است.'
                . ($tenant->suspended_reason ? ' دلیل: ' . $tenant->suspended_reason : '');
        }

        return 'حساب شما به هیچ سازمانی متصل نیست. با پشتیبانی تماس بگیرید.';
    }
}
