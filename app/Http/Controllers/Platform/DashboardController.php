<?php

namespace App\Http\Controllers\Platform;

use App\Console\Commands\ExpireSubscriptions;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * داشبورد نظارتی پلتفرم — فقط خواندنی و فقط سطح‌بالا.
 *
 * برای آمار بین‌سازمانی عمداً از DB::table استفاده می‌شود، نه از مدل با
 * withoutScope: در کنترلرهای Platform هر بار که global scope خاموش شود، یک اشتباه
 * کوچک یعنی قاطی شدن داده‌ی همه‌ی سازمان‌ها در یک صفحه.
 */
class DashboardController extends Controller
{
    public function index()
    {
        $counts = Tenant::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $paid = fn () => DB::table('payments')->where('status', Payment::STATUS_PAID);

        $totalRevenue = (int) $paid()->sum('amount');
        $monthRevenue = (int) $paid()->where('paid_at', '>=', now()->startOfMonth())->sum('amount');

        $recentPayments = DB::table('payments')
            ->join('tenants', 'tenants.id', '=', 'payments.tenant_id')
            ->where('payments.status', Payment::STATUS_PAID)
            ->orderByDesc('payments.paid_at')
            ->limit(10)
            ->get([
                'payments.id', 'payments.amount', 'payments.days_granted', 'payments.ref_id',
                'payments.paid_at', 'tenants.name as tenant_name', 'tenants.id as tenant_id',
            ]);

        $expiringSoon = Tenant::where('status', Tenant::STATUS_ACTIVE)
            ->where('is_unlimited', false)
            ->whereNotNull('subscription_ends_at')
            ->whereBetween('subscription_ends_at', [now(), now()->addDays(7)])
            ->orderBy('subscription_ends_at')
            ->get();

        $connectedBots = Tenant::whereNotNull('bot_connected_at')->count();

        // داده‌ی سازمان منقضی حذف نمی‌شود؛ فقط بعد از شش ماه به سوپرادمین یادآوری می‌شود.
        $purgeCandidates = Tenant::where('status', Tenant::STATUS_EXPIRED)
            ->where('is_unlimited', false)
            ->whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '<', now()->subMonths(ExpireSubscriptions::PURGE_WARNING_MONTHS))
            ->orderBy('subscription_ends_at')
            ->get();

        return view('platform.dashboard', compact(
            'counts', 'totalRevenue', 'monthRevenue', 'recentPayments',
            'expiringSoon', 'connectedBots', 'purgeCandidates'
        ));
    }
}
