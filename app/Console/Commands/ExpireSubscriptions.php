<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;

/**
 * انقضای روزانه‌ی اشتراک‌ها.
 *
 *   php artisan subscriptions:expire
 *
 * سازمانی که اشتراکش تمام شده: وضعیت expired می‌شود و وبهوک رباتش برداشته می‌شود.
 * **توکن ربات پاک نمی‌شود** تا با تمدید، ربات بدون دخالت کاربر برگردد.
 *
 * ستون status عمداً واقعی است (نه محاسبه‌شده) و همین دستور هم‌گامش می‌کند؛
 * میدلور ResolveTenant هم موقع ورود کاربر همین کار را تنبل انجام می‌دهد.
 */
class ExpireSubscriptions extends Command
{
    /** بعد از این مدت از انقضا، سازمان کاندیدای حذف گزارش می‌شود (حذف همچنان دستی است) */
    public const PURGE_WARNING_MONTHS = 6;

    protected $signature   = 'subscriptions:expire';
    protected $description = 'غیرفعال کردن سازمان‌هایی که اشتراکشان تمام شده است';

    public function handle(SubscriptionService $subscriptions): int
    {
        $expired = Tenant::where('status', Tenant::STATUS_ACTIVE)
            ->where('is_unlimited', false)
            ->whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '<', now())
            ->get();

        foreach ($expired as $tenant) {
            $subscriptions->expire($tenant);
            $this->warn("tenant#{$tenant->id} ({$tenant->name}) منقضی شد؛ وبهوک ربات برداشته شد.");
        }

        $this->info($expired->isEmpty() ? 'سازمان منقضی‌شده‌ای نبود.' : "{$expired->count()} سازمان منقضی شد.");

        $this->reportPurgeCandidates();

        return self::SUCCESS;
    }

    /**
     * سازمان‌هایی که بیش از شش ماه است منقضی‌اند.
     * داده‌شان عمداً حذف نمی‌شود — فقط به سوپرادمین گزارش می‌شود تا خودش تصمیم بگیرد.
     */
    private function reportPurgeCandidates(): void
    {
        $candidates = Tenant::where('status', Tenant::STATUS_EXPIRED)
            ->where('is_unlimited', false)
            ->whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '<', now()->subMonths(self::PURGE_WARNING_MONTHS))
            ->orderBy('subscription_ends_at')
            ->get();

        if ($candidates->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->warn('سازمان‌هایی که بیش از ' . self::PURGE_WARNING_MONTHS . ' ماه است منقضی‌اند (کاندیدای حذف دستی):');

        foreach ($candidates as $tenant) {
            $this->line("  tenant#{$tenant->id} — {$tenant->name} — پایان اشتراک: {$tenant->subscription_ends_at->toDateString()}");
        }
    }
}
