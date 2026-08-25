<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** فقط سوپرادمین پلتفرم (مالک این نصب) اجازه‌ی ورود به ناحیه‌ی /platform را دارد */
class PlatformAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        if (!Auth::user()->isPlatformAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        // تضمین: در طول یک درخواست platform هیچ‌وقت TenantContext برای کل درخواست
        // ست نمی‌ماند. صفحات نظارتی داده‌ی مستأجر را فقط داخل closure های
        // TenantContext::forTenant می‌خوانند و بعدش زمینه پاک می‌شود.
        TenantContext::forget();

        $response = $next($request);

        TenantContext::forget();

        return $response;
    }
}
