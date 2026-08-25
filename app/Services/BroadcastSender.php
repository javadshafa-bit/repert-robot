<?php

namespace App\Services;

use App\Models\BroadcastMessage;
use App\Models\Tenant;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BroadcastSender
{
    private string $apiUrl = '';

    /**
     * ارسال پیام به همه گیرندگان و به‌روزرسانی آمار.
     * توکن ربات از رکورد مستأجرِ همین پیام خوانده می‌شود، نه از تنظیمات سراسری.
     */
    public function send(BroadcastMessage $message): void
    {
        $tenant = Tenant::find($message->tenant_id);

        if (!$tenant || !$tenant->botIsUsable()) {
            Log::warning("Broadcast #{$message->id} skipped: tenant#{$message->tenant_id} bot is not usable.");
            return;
        }

        $this->apiUrl = $tenant->botApiUrl();

        $sent   = 0;
        $failed = 0;

        // گیرندگان باید در بستر همان مستأجر خوانده شوند
        $recipients = TenantContext::forTenant($tenant, fn() => $message->recipientsQuery()->get());

        foreach ($recipients as $rep) {
            try {
                $ok = $message->photo_path
                    ? $this->sendPhoto($rep->chat_id, $message->photo_path, $message->body)
                    : $this->sendText($rep->chat_id, $message->body);
                $ok ? $sent++ : $failed++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning("Broadcast #{$message->id} to chat {$rep->chat_id} failed: {$e->getMessage()}");
            }
        }

        $message->update([
            'sent_count'     => $message->sent_count + $sent,
            'failed_count'   => $message->failed_count + $failed,
            'last_sent_at'   => now(),
            'last_sent_date' => Carbon::now(BroadcastMessage::TIMEZONE)->format('Y-m-d'),
            'status'         => in_array($message->schedule_type, ['instant', 'once']) ? 'sent' : $message->status,
        ]);
    }

    private function sendText(string $chatId, string $text): bool
    {
        $res = Http::timeout(15)->post($this->apiUrl . 'sendMessage', [
            'chat_id' => $chatId,
            'text'    => $text,
        ]);
        return $res->successful() && ($res->json('ok') === true);
    }

    private function sendPhoto(string $chatId, string $photoPath, string $caption): bool
    {
        if (!Storage::disk('public')->exists($photoPath)) {
            return false;
        }
        $res = Http::timeout(30)
            ->attach('photo', Storage::disk('public')->get($photoPath), basename($photoPath))
            ->post($this->apiUrl . 'sendPhoto', [
                'chat_id' => $chatId,
                'caption' => $caption,
            ]);
        return $res->successful() && ($res->json('ok') === true);
    }
}
