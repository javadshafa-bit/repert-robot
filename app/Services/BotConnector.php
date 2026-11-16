<?php

namespace App\Services;

use App\Models\Tenant;
use App\Support\Messenger;
use Illuminate\Support\Facades\Log;

/**
 * مدیریت اتصال ربات‌های هر مستأجر (setWebhook / deleteWebhook / getMe) روی بله و تلگرام.
 * هر مستأجر برای هر پیام‌رسان توکن و مسیر وبهوک اختصاصی خودش را دارد.
 *
 * پارامتر $platform همه‌جا پیش‌فرض «بله» است تا صدا زدن‌های قدیمی بدون تغییر کار کنند.
 */
class BotConnector
{
    /** ثبت وبهوک اختصاصی این مستأجر روی پیام‌رسان */
    public function setWebhook(Tenant $tenant, string $platform = Messenger::BALE): array
    {
        $token = $tenant->tokenFor($platform);
        $label = Messenger::label($platform);

        if (!$token) {
            return ['ok' => false, 'message' => "توکن ربات {$label} ثبت نشده است."];
        }

        $url     = $platform === Messenger::TELEGRAM ? $tenant->telegramWebhookUrl() : $tenant->webhookUrl();
        $payload = ['url' => $url];

        if ($platform === Messenger::TELEGRAM) {
            // تلگرام این رمز را در هدر هر آپدیت برمی‌گرداند و کنترلر آن را چک می‌کند
            $payload['secret_token']    = $tenant->telegramSecretToken();
            $payload['allowed_updates'] = json_encode(['message', 'edited_message', 'callback_query']);
            $payload['drop_pending_updates'] = true;
        }

        try {
            $response = Messenger::http($platform, 20)
                ->post(Messenger::apiUrl($platform, $token) . 'setWebhook', $payload);
        } catch (\Throwable $e) {
            Log::warning("tenant#{$tenant->id} setWebhook({$platform}) failed: {$e->getMessage()}");
            return ['ok' => false, 'message' => "ارتباط با سرور {$label} برقرار نشد."];
        }

        if (!($response->successful() && $response->json('ok'))) {
            return ['ok' => false, 'message' => $response->json('description', 'خطای ناشناخته')];
        }

        $username = $this->fetchUsername($tenant, $platform);

        $tenant->forceFill($platform === Messenger::TELEGRAM
            ? ['telegram_connected_at' => now(), 'telegram_bot_username' => $username]
            : ['bot_connected_at' => now(), 'bot_username' => $username]
        )->save();

        return ['ok' => true, 'message' => "ربات {$label} با موفقیت متصل شد.", 'url' => $url];
    }

    /** حذف وبهوک (ربات دیگر آپدیتی دریافت نمی‌کند) */
    public function deleteWebhook(Tenant $tenant, string $platform = Messenger::BALE): bool
    {
        $token = $tenant->tokenFor($platform);
        if (!$token) {
            return false;
        }

        try {
            $response = Messenger::http($platform, 20)
                ->post(Messenger::apiUrl($platform, $token) . 'deleteWebhook');
        } catch (\Throwable $e) {
            Log::warning("tenant#{$tenant->id} deleteWebhook({$platform}) failed: {$e->getMessage()}");
            return false;
        }

        return $response->successful() && (bool) $response->json('ok');
    }

    /** ثبت وبهوک همه‌ی پیام‌رسان‌هایی که توکن دارند */
    public function setAllWebhooks(Tenant $tenant): array
    {
        $results = [];
        foreach ($tenant->botPlatforms() as $platform) {
            $results[$platform] = $this->setWebhook($tenant, $platform);
        }

        return $results;
    }

    /** حذف وبهوک همه‌ی پیام‌رسان‌ها (توکن‌ها می‌مانند) */
    public function deleteAllWebhooks(Tenant $tenant): void
    {
        foreach ($tenant->botPlatforms() as $platform) {
            $this->deleteWebhook($tenant, $platform);
        }
    }

    /**
     * قطع کامل اتصال: حذف وبهوک + پاک کردن توکن ذخیره‌شده.
     *
     * فقط وقتی صدا زده شود که کاربر آگاهانه ربات را جدا می‌کند. برای غیرفعال‌سازی
     * موقت (تعلیق، انقضای اشتراک) از deleteWebhook استفاده کن تا توکن سر جایش بماند
     * و با برگشتن سازمان، ربات بدون وارد کردن دوباره‌ی توکن زنده شود.
     */
    public function disconnect(Tenant $tenant, string $platform = Messenger::BALE): void
    {
        $this->deleteWebhook($tenant, $platform);

        $tenant->forceFill($platform === Messenger::TELEGRAM
            ? ['telegram_bot_token' => null, 'telegram_bot_username' => null, 'telegram_connected_at' => null]
            : ['bot_token' => null, 'bot_username' => null, 'bot_connected_at' => null]
        )->save();
    }

    /**
     * آدرسی که همین حالا روی پیام‌رسان ثبت شده است (برای مقایسه با چیزی که ما فرستادیم).
     * null یعنی نتوانستیم بپرسیم؛ رشته‌ی خالی یعنی هیچ وبهوکی ثبت نیست.
     */
    public function registeredWebhookUrl(Tenant $tenant, string $platform = Messenger::BALE): ?string
    {
        $token = $tenant->tokenFor($platform);
        if (!$token) {
            return null;
        }

        try {
            $response = Messenger::http($platform, 15)->get(Messenger::apiUrl($platform, $token) . 'getWebhookInfo');
        } catch (\Throwable $e) {
            return null;
        }

        if (!($response->successful() && $response->json('ok'))) {
            return null;
        }

        return (string) $response->json('result.url', '');
    }

    public function fetchUsername(Tenant $tenant, string $platform = Messenger::BALE): ?string
    {
        $token = $tenant->tokenFor($platform);
        if (!$token) {
            return null;
        }

        try {
            $response = Messenger::http($platform, 15)->get(Messenger::apiUrl($platform, $token) . 'getMe');
        } catch (\Throwable $e) {
            return null;
        }

        return $response->successful() ? $response->json('result.username') : null;
    }
}
