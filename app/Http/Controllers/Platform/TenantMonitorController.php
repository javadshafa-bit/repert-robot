<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\BroadcastMessage;
use App\Models\Category;
use App\Models\Department;
use App\Models\Payment;
use App\Models\Report;
use App\Models\Representative;
use App\Models\SubscriptionLog;
use App\Models\Tenant;
use App\Support\TenantContext;

/**
 * صفحات نظارتی یک سازمان — همه فقط‌خواندنی.
 *
 * قاعده‌ی ثابت: داده‌ی مستأجر فقط داخل TenantContext::forTenant خوانده می‌شود،
 * هرگز با withoutScope. اگر جایی این را بشکنیم، یک صفحه می‌تواند بی‌سروصدا داده‌ی
 * همه‌ی سازمان‌ها را کنار هم بگذارد. مستأجر هم فقط داخل همان closure ست می‌ماند،
 * نه برای کل طول درخواست.
 *
 * هیچ متد نوشتنی‌ای اینجا وجود ندارد و نباید اضافه شود.
 */
class TenantMonitorController extends Controller
{
    public function representatives(Tenant $tenant)
    {
        $rows = TenantContext::forTenant($tenant, fn () => Representative::with('province')
            ->orderBy('last_name')
            ->paginate(30));

        return view('platform.tenants.monitor.representatives', compact('tenant', 'rows'));
    }

    public function departments(Tenant $tenant)
    {
        $rows = TenantContext::forTenant($tenant, fn () => Department::withCount('reports')
            ->orderBy('sort_order')
            ->paginate(30));

        return view('platform.tenants.monitor.departments', compact('tenant', 'rows'));
    }

    public function categories(Tenant $tenant)
    {
        $rows = TenantContext::forTenant($tenant, fn () => Category::withCount('fields')
            ->orderBy('sort_order')
            ->paginate(30));

        return view('platform.tenants.monitor.categories', compact('tenant', 'rows'));
    }

    public function reports(Tenant $tenant)
    {
        $rows = TenantContext::forTenant($tenant, fn () => Report::with(['representative', 'category', 'department'])
            ->latest()
            ->paginate(30));

        return view('platform.tenants.monitor.reports', compact('tenant', 'rows'));
    }

    public function report(Tenant $tenant, int $report)
    {
        // route model binding اینجا کار نمی‌کند: پیش از ست شدن مستأجر اجرا می‌شود
        // و global scope هیچ رکوردی برنمی‌گرداند. پس داخل همین closure پیدایش می‌کنیم.
        $row = TenantContext::forTenant($tenant, fn () => Report::with(['representative.province', 'category', 'department'])
            ->findOrFail($report));

        return view('platform.tenants.monitor.report', compact('tenant', 'row'));
    }

    public function broadcasts(Tenant $tenant)
    {
        $rows = TenantContext::forTenant($tenant, fn () => BroadcastMessage::latest()->paginate(30));

        return view('platform.tenants.monitor.broadcasts', compact('tenant', 'rows'));
    }

    public function payments(Tenant $tenant)
    {
        $rows = TenantContext::forTenant($tenant, fn () => Payment::with('discountCode')->latest()->paginate(30));

        return view('platform.tenants.monitor.payments', compact('tenant', 'rows'));
    }

    public function subscriptionLogs(Tenant $tenant)
    {
        $rows = TenantContext::forTenant($tenant, fn () => SubscriptionLog::with('user')->latest()->paginate(30));

        return view('platform.tenants.monitor.logs', compact('tenant', 'rows'));
    }
}
