<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * مدیریت اتصال ربات بله‌ی هر مستأجر (setWebhook / deleteWebhook / getMe).
 * هر مستأجر توکن و مسیر وبهوک اختصاصی خودش را دارد.
 */
class BotConnector
{
    /** ثبت وبهوک اختصاصی این مستأجر روی بله */
    public function setWebhook(Tenant $tenant): array
    {
        if (!$tenant->bot_token) {
            return ['ok' => false, 'message' => 'توکن ربات ثبت نشده است.'];
        }

        $url = $tenant->webhookUrl();

        try {
            $response = Http::timeout(20)
                ->post("https://tapi.bale.ai/bot{$tenant->bot_token}/setWebhook", ['url' => $url]);
        } catch (\Throwable $e) {
            Log::warning("tenant#{$tenant->id} setWebhook failed: {$e->getMessage()}");
            return ['ok' => false, 'message' => 'ارتباط با سرور بله برقرار نشد.'];
        }

        if (!($response->successful() && $response->json('ok'))) {
            return ['ok' => false, 'message' => $response->json('description', 'خطای ناشناخته')];
        }

        $tenant->forceFill([
            'bot_connected_at' => now(),
            'bot_username'     => $this->fetchUsername($tenant),
        ])->save();

        return ['ok' => true, 'message' => 'ربات با موفقیت متصل شد.', 'url' => $url];
    }

    /** حذف وبهوک روی بله (ربات دیگر آپدیتی دریافت نمی‌کند) */
    public function deleteWebhook(Tenant $tenant): bool
    {
        if (!$tenant->bot_token) {
            return false;
        }

        try {
            $response = Http::timeout(20)
                ->post("https://tapi.bale.ai/bot{$tenant->bot_token}/deleteWebhook");
        } catch (\Throwable $e) {
            Log::warning("tenant#{$tenant->id} deleteWebhook failed: {$e->getMessage()}");
            return false;
        }

        return $response->successful() && (bool) $response->json('ok');
    }

    /**
     * قطع کامل اتصال: حذف وبهوک + پاک کردن توکن ذخیره‌شده.
     *
     * فقط وقتی صدا زده شود که کاربر آگاهانه ربات را جدا می‌کند. برای غیرفعال‌سازی
     * موقت (تعلیق، انقضای اشتراک) از deleteWebhook استفاده کن تا توکن سر جایش بماند
     * و با برگشتن سازمان، ربات بدون وارد کردن دوباره‌ی توکن زنده شود.
     */
    public function disconnect(Tenant $tenant): void
    {
        $this->deleteWebhook($tenant);

        $tenant->forceFill([
            'bot_token'        => null,
            'bot_username'     => null,
            'bot_connected_at' => null,
        ])->save();
    }

    /**
     * آدرسی که همین حالا روی بله ثبت شده است (برای مقایسه با چیزی که ما فرستادیم).
     * null یعنی نتوانستیم بپرسیم؛ رشته‌ی خالی یعنی هیچ وبهوکی ثبت نیست.
     */
    public function registeredWebhookUrl(Tenant $tenant): ?string
    {
        if (!$tenant->bot_token) {
            return null;
        }

        try {
            $response = Http::timeout(15)->get("https://tapi.bale.ai/bot{$tenant->bot_token}/getWebhookInfo");
        } catch (\Throwable $e) {
            return null;
        }

        if (!($response->successful() && $response->json('ok'))) {
            return null;
        }

        return (string) $response->json('result.url', '');
    }

    public function fetchUsername(Tenant $tenant): ?string
    {
        if (!$tenant->bot_token) {
            return null;
        }

        try {
            $response = Http::timeout(15)->get("https://tapi.bale.ai/bot{$tenant->bot_token}/getMe");
        } catch (\Throwable $e) {
            return null;
        }

        return $response->successful() ? $response->json('result.username') : null;
    }
}
