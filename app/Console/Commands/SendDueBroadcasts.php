<?php

namespace App\Console\Commands;

use App\Models\BroadcastMessage;
use App\Services\BroadcastSender;
use Illuminate\Console\Command;

class SendDueBroadcasts extends Command
{
    protected $signature   = 'broadcast:send-due';
    protected $description = 'ارسال پیام‌های همگانی که موعدشان رسیده است';

    public function handle(BroadcastSender $sender): int
    {
        $candidates = BroadcastMessage::query()
            ->where(function ($q) {
                $q->where(fn($q) => $q->where('schedule_type', 'once')->where('status', 'pending'))
                  ->orWhere(fn($q) => $q->whereIn('schedule_type', ['weekly', 'monthly_jalali'])->where('status', 'active'));
            })
            ->get();

        $count = 0;
        foreach ($candidates as $message) {
            if ($message->isDue()) {
                $sender->send($message);
                $count++;
                $this->info("Broadcast #{$message->id} sent.");
            }
        }

        $this->info("Done. {$count} broadcast(s) sent.");
        return self::SUCCESS;
    }
}
