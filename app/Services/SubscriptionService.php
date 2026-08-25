<?php

namespace App\Services;

use App\Models\SubscriptionLog;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Support\Carbon;

/**
 * تنها جایی که دوره‌ی اشتراک یک سازمان عوض می‌شود.
 *
 * هر تغییر: (۱) وضعیت را هم‌گام می‌کند، (۲) در subscription_logs ثبت می‌شود،
 * (۳) در صورت لزوم وبهوک ربات را برمی‌دارد یا برمی‌گرداند — بدون دست زدن به توکن.
 */
class SubscriptionService
{
    public function __construct(private BotConnector $bot) {}

    /**
     * افزودن روز بابت یک پرداخت موفق.
     * مبنا max(now, پایان فعلی) است تا تمدید زودهنگام روزهای باقیمانده را نسوزاند.
     */
    public function grantDays(Tenant $tenant, int $days, ?int $userId = null, ?string $note = null): void
    {
        $this->apply(
            $tenant,
            fn () => $tenant->forceFill([
                'subscription_ends_at' => $tenant->extendedEndsAt($days),
            ]),
            SubscriptionLog::SOURCE_PAYMENT,
            $userId,
            $note ?? "افزودن {$days} روز بابت پرداخت"
        );
    }

    /** تعیین دستی دوره توسط سوپرادمین (تمدید، کوتاه کردن، یا نامحدود کردن) */
    public function setManually(Tenant $tenant, ?Carbon $endsAt, bool $unlimited, ?int $userId, ?string $note = null): void
    {
        $this->apply(
            $tenant,
            fn () => $tenant->forceFill([
                // تاریخ نگه داشته می‌شود حتی وقتی «نامحدود» تیک خورده، تا اگر بعداً
                // نامحدود برداشته شد، دوره‌ی قبلی برگردد نه اینکه سازمان یک‌دفعه منقضی شود.
                'subscription_ends_at' => $endsAt ?? $tenant->subscription_ends_at,
                'is_unlimited'         => $unlimited,
            ]),
            SubscriptionLog::SOURCE_MANUAL,
            $userId,
            $note
        );
    }

    /** کرون انقضا: اشتراک تمام شده است */
    public function expire(Tenant $tenant): void
    {
        $this->apply($tenant, fn () => null, SubscriptionLog::SOURCE_EXPIRE, null, 'انقضای خودکار اشتراک');
    }

    /** تعلیق دستی — توکن دست‌نخورده می‌ماند */
    public function suspend(Tenant $tenant, ?int $userId, ?string $reason = null): void
    {
        $this->apply(
            $tenant,
            function () use ($tenant, $reason) {
                $tenant->forceFill([
                    'status'           => Tenant::STATUS_SUSPENDED,
                    'suspended_at'     => now(),
                    'suspended_reason' => $reason,
                ]);
            },
            SubscriptionLog::SOURCE_MANUAL,
            $userId,
            $reason ? "تعلیق: {$reason}" : 'تعلیق سازمان'
        );
    }

    /** رفع تعلیق — وضعیت از روی اشتراک دوباره حساب می‌شود */
    public function resume(Tenant $tenant, ?int $userId): void
    {
        $this->apply(
            $tenant,
            function () use ($tenant) {
                $tenant->forceFill([
                    'status'           => Tenant::STATUS_ACTIVE, // موقت؛ intendedStatus اصلاحش می‌کند
                    'suspended_at'     => null,
                    'suspended_reason' => null,
                ]);
            },
            SubscriptionLog::SOURCE_MANUAL,
            $userId,
            'رفع تعلیق سازمان'
        );
    }

    /**
     * هسته‌ی مشترک: عکس قبل، اعمال تغییر، هم‌گام‌سازی وضعیت، اثر روی ربات، ثبت لاگ.
     */
    private function apply(Tenant $tenant, callable $mutate, string $source, ?int $userId, ?string $note): void
    {
        $before = [
            'ends_at'   => $tenant->subscription_ends_at?->copy(),
            'unlimited' => (bool) $tenant->is_unlimited,
            'status'    => $tenant->status,
        ];

        $mutate();

        $tenant->status = $tenant->intendedStatus();
        $tenant->save();

        $this->syncBot($tenant, $before['status']);

        TenantContext::forTenant($tenant, fn () => SubscriptionLog::create([
            'user_id'        => $userId,
            'source'         => $source,
            'from_ends_at'   => $before['ends_at'],
            'to_ends_at'     => $tenant->subscription_ends_at,
            'from_unlimited' => $before['unlimited'],
            'to_unlimited'   => (bool) $tenant->is_unlimited,
            'from_status'    => $before['status'],
            'to_status'      => $tenant->status,
            'note'           => $note,
        ]));
    }

    /**
     * ربات را با وضعیت جدید هم‌گام کن.
     *
     * غیرفعال شدن → فقط deleteWebhook (توکن می‌ماند تا با تمدید، ربات بدون دخالت کاربر برگردد).
     * فعال شدن دوباره → ثبت مجدد وبهوک. این کار عمداً از سرویس انجام می‌شود و نه از
     * کنترلرهای Platform؛ سوپرادمین حق اتصال ربات ندارد.
     */
    private function syncBot(Tenant $tenant, string $previousStatus): void
    {
        if (!$tenant->bot_token) {
            return;
        }

        $wasUsable = $previousStatus === Tenant::STATUS_ACTIVE;
        $isUsable  = $tenant->status === Tenant::STATUS_ACTIVE;

        if ($wasUsable && !$isUsable) {
            $this->bot->deleteWebhook($tenant);

            return;
        }

        if (!$wasUsable && $isUsable) {
            $this->bot->setWebhook($tenant);
        }
    }
}
