<?php

namespace App\Services;

use App\Models\BroadcastMessage;
use App\Models\Tenant;
use App\Support\Messenger;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BroadcastSender
{
    /**
     * ارسال پیام به همه گیرندگان و به‌روزرسانی آمار.
     *
     * توکن‌ها از رکورد مستأجرِ همین پیام خوانده می‌شوند. نماینده‌ای که هم در بله و هم
     * در تلگرام وصل است، پیام را در هر دو می‌گیرد (هر ارسال جدا شمرده می‌شود).
     */
    public function send(BroadcastMessage $message): void
    {
        $tenant = Tenant::find($message->tenant_id);

        if (!$tenant || !$tenant->anyBotIsUsable()) {
            Log::warning("Broadcast #{$message->id} skipped: tenant#{$message->tenant_id} has no usable bot.");
            return;
        }

        // [platform => apiUrl] فقط برای ربات‌هایی که الان قابل استفاده‌اند
        $channels = [];
        foreach (Messenger::ALL as $platform) {
            if ($tenant->botIsUsable($platform)) {
                $channels[$platform] = $tenant->botApiUrl($platform);
            }
        }

        $sent   = 0;
        $failed = 0;

        // گیرندگان باید در بستر همان مستأجر خوانده شوند
        $recipients = TenantContext::forTenant($tenant, fn() => $message->recipientsQuery()->get());

        foreach ($recipients as $rep) {
            foreach ($channels as $platform => $apiUrl) {
                $chatKey = $rep->{Messenger::repColumn($platform)};
                if (!filled($chatKey)) {
                    continue;
                }

                $rawChatId = Messenger::rawChatId($chatKey);

                try {
                    $ok = $message->photo_path
                        ? $this->sendPhoto($platform, $apiUrl, $rawChatId, $message->photo_path, $message->body)
                        : $this->sendText($platform, $apiUrl, $rawChatId, $message->body);
                    $ok ? $sent++ : $failed++;
                } catch (\Throwable $e) {
                    $failed++;
                    Log::warning("Broadcast #{$message->id} to {$platform} chat {$rawChatId} failed: {$e->getMessage()}");
                }
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

    private function sendText(string $platform, string $apiUrl, string $chatId, string $text): bool
    {
        $res = Messenger::http($platform, 15)->post($apiUrl . 'sendMessage', [
            'chat_id' => $chatId,
            'text'    => $text,
        ]);
        return $res->successful() && ($res->json('ok') === true);
    }

    private function sendPhoto(string $platform, string $apiUrl, string $chatId, string $photoPath, string $caption): bool
    {
        if (!Storage::disk('public')->exists($photoPath)) {
            return false;
        }
        $res = Messenger::http($platform, 30)
            ->attach('photo', Storage::disk('public')->get($photoPath), basename($photoPath))
            ->post($apiUrl . 'sendPhoto', [
                'chat_id' => $chatId,
                'caption' => $caption,
            ]);
        return $res->successful() && ($res->json('ok') === true);
    }
}
