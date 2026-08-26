<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;

/**
 * پاک کردن مستأجرِ باقی‌مانده در ابتدای هر درخواست.
 *
 * چرا لازم است: `TenantContext` حالت static دارد و میدلور `SubstituteBindings`
 * (که route model binding را انجام می‌دهد) **پیش از** میدلورهای `tenant` و
 * `platform.admin` اجرا می‌شود. اگر مستأجر درخواست قبلی هنوز نشسته باشد،
 * global scope موقع binding با مستأجر اشتباه اعمال می‌شود و رکورد سازمان دیگری
 * پیدا می‌شود.
 *
 * با php-fpm هر درخواست پروسه‌ی تازه است و این اتفاق نمی‌افتد، ولی زیر Octane یا
 * هر ران‌تایم ماندگار دقیقاً همین نشت رخ می‌دهد. این میدلور جلوی همه‌ی حالت‌ها را
 * می‌گیرد و باید اولین میدلور پشته باشد.
 */
class ResetTenantContext
{
    public function handle(Request $request, Closure $next)
    {
        TenantContext::forget();

        return $next($request);
    }
}
