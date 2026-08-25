<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use App\Support\JalaliDate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * نظارت سوپرادمین پلتفرم بر سازمان‌ها.
 *
 * حق ندارد: ربات وصل کند، توکن ببیند/وارد کند، یا داده‌ی کسب‌وکاری کسی را تغییر دهد.
 * می‌تواند: ببیند، اشتراک را تنظیم کند، تعلیق/رفع تعلیق کند.
 */
class TenantController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function index(Request $request)
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('q', ''));

        if ($status !== null && !array_key_exists($status, Tenant::statusLabels())) {
            $status = null;
        }

        $tenants = Tenant::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('owner_name', 'like', "%{$search}%");
            }))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $counts = Tenant::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('platform.tenants.index', compact('tenants', 'status', 'search', 'counts'));
    }

    public function show(Tenant $tenant)
    {
        // آمار سطح‌بالا؛ صفحات نظارتیِ جزئیات در مسیرهای جدا و فقط‌خواندنی‌اند.
        $stats = [
            'users'           => DB::table('users')->where('tenant_id', $tenant->id)->count(),
            'representatives' => DB::table('representatives')->where('tenant_id', $tenant->id)->count(),
            'reports'         => DB::table('reports')->where('tenant_id', $tenant->id)->count(),
            'categories'      => DB::table('categories')->where('tenant_id', $tenant->id)->count(),
            'departments'     => DB::table('departments')->where('tenant_id', $tenant->id)->count(),
        ];

        $payments = DB::table('payments')
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $logs = DB::table('subscription_logs')
            ->leftJoin('users', 'users.id', '=', 'subscription_logs.user_id')
            ->where('subscription_logs.tenant_id', $tenant->id)
            ->orderByDesc('subscription_logs.created_at')
            ->limit(20)
            ->get(['subscription_logs.*', 'users.name as actor_name']);

        return view('platform.tenants.show', compact('tenant', 'stats', 'payments', 'logs'));
    }

    /**
     * تعیین دستی دوره‌ی اشتراک: تمدید سریع، تاریخ دقیق، یا نامحدود کردن.
     * این تنها کار «نوشتنی» سوپرادمین روی یک سازمان است (جز تعلیق) و همیشه لاگ می‌شود.
     */
    public function subscription(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'mode'         => 'required|in:extend,set',
            'days'         => 'required_if:mode,extend|nullable|integer|min:1|max:3650',
            'ends_at'      => 'nullable|string|max:20',
            'is_unlimited' => 'nullable|boolean',
            'note'         => 'nullable|string|max:500',
        ], [
            'days.required_if' => 'تعداد روز مشخص نیست.',
            'days.max'         => 'حداکثر ۳۶۵۰ روز در یک مرحله.',
        ]);

        $unlimited = $request->boolean('is_unlimited');

        if ($data['mode'] === 'extend') {
            $endsAt = $tenant->extendedEndsAt((int) $data['days']);
            $note   = ($data['note'] ?? null) ?: "افزودن {$data['days']} روز به‌صورت دستی";
        } else {
            try {
                $endsAt = JalaliDate::parse($data['ends_at'] ?? null, endOfDay: true);
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages(['ends_at' => $e->getMessage()]);
            }

            if (!$unlimited && $endsAt === null) {
                throw ValidationException::withMessages([
                    'ends_at' => 'یا تاریخ پایان را وارد کنید یا گزینه‌ی نامحدود را بزنید.',
                ]);
            }

            $note = ($data['note'] ?? null) ?: ($unlimited ? 'اشتراک نامحدود شد' : 'تعیین دستی تاریخ پایان');
        }

        $this->subscriptions->setManually($tenant, $endsAt, $unlimited, $request->user()->id, $note);

        return back()->with('success', 'دوره‌ی اشتراک به‌روزرسانی شد و در تاریخچه ثبت گردید.');
    }

    public function suspend(Request $request, Tenant $tenant)
    {
        $data = $request->validate(['reason' => 'nullable|string|max:1000']);

        $this->subscriptions->suspend($tenant, $request->user()->id, $data['reason'] ?? null);

        return back()->with('success', 'سازمان معلق شد و وبهوک رباتش حذف گردید (توکن دست‌نخورده ماند).');
    }

    public function resume(Request $request, Tenant $tenant)
    {
        $this->subscriptions->resume($tenant, $request->user()->id);

        $message = $tenant->fresh()->hasActiveSubscription()
            ? 'سازمان از حالت تعلیق خارج شد و ربات دوباره فعال شد.'
            : 'سازمان از حالت تعلیق خارج شد، ولی اشتراک معتبری ندارد و تا تمدید، پنل و ربات فعال نمی‌شود.';

        return back()->with('success', $message);
    }
}
