<?php

namespace App\Console\Commands;

use App\Models\BroadcastMessage;
use App\Models\Tenant;
use App\Services\BroadcastSender;
use App\Support\TenantContext;
use Illuminate\Console\Command;

class SendDueBroadcasts extends Command
{
    protected $signature   = 'broadcast:send-due';
    protected $description = 'ارسال پیام‌های همگانی که موعدشان رسیده است';

    public function handle(BroadcastSender $sender): int
    {
        $count = 0;

        // این دستور با کرون اجرا می‌شود و کاربر لاگین‌شده‌ای ندارد؛
        // پس باید روی هر مستأجر جداگانه لوپ بزند و TenantContext را خودش ست کند.
        $tenants = Tenant::where('status', Tenant::STATUS_ACTIVE)
            ->whereNotNull('bot_token')
            ->get();

        foreach ($tenants as $tenant) {
            $count += TenantContext::forTenant($tenant, function () use ($sender, $tenant) {
                $sent = 0;

                $candidates = BroadcastMessage::query()
                    ->where(function ($q) {
                        $q->where(fn($q) => $q->where('schedule_type', 'once')->where('status', 'pending'))
                          ->orWhere(fn($q) => $q->whereIn('schedule_type', ['weekly', 'monthly_jalali'])->where('status', 'active'));
                    })
                    ->get();

                foreach ($candidates as $message) {
                    if ($message->isDue()) {
                        $sender->send($message);
                        $sent++;
                        $this->info("Tenant #{$tenant->id}: broadcast #{$message->id} sent.");
                    }
                }

                return $sent;
            });
        }

        $this->info("Done. {$count} broadcast(s) sent across {$tenants->count()} tenant(s).");
        return self::SUCCESS;
    }
}
