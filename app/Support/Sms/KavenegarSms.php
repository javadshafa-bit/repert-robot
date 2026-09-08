<?php

namespace App\Support\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ارسال کد یک‌بارمصرف با API «وریفای» کاوه‌نگار.
 *
 * چرا verify/lookup و نه ارسال معمولی:
 *  - متن از الگوی از پیش تأییدشده می‌آید، پس خط اختصاصی و تأیید متن لازم نیست
 *  - صفِ این API از پیامک تبلیغاتی جداست و سریع‌تر می‌رسد
 *  - فقط یک %token جای می‌گیرد، پس امکان تزریق متن دلخواه وجود ندارد
 */
class KavenegarSms implements SmsSender
{
    public function __construct(
        private ?string $apiKey,
        private ?string $template,
        private int $timeout = 10,
    ) {}

    public function sendOtp(string $phoneNumber, string $code): bool
    {
        if (!$this->isConfigured()) {
            Log::error('[SMS:kavenegar] اعتبار تنظیم نشده — ارسال انجام نشد');
            return false;
        }

        $url = sprintf('https://api.kavenegar.com/v1/%s/verify/lookup.json', $this->apiKey);

        try {
            $response = Http::timeout($this->timeout)->asForm()->post($url, [
                'receptor' => $phoneNumber,
                'token'    => $code,
                'template' => $this->template,
            ]);
        } catch (Throwable $e) {
            // پیام استثنا ممکن است URL و در نتیجه کلید API را داشته باشد
            Log::error('[SMS:kavenegar] ارتباط برقرار نشد', [
                'phone' => $this->mask($phoneNumber),
                'error' => $e::class,
            ]);
            return false;
        }

        $status = (int) ($response->json('return.status') ?? 0);

        if ($status !== 200) {
            // نه کد، نه کلید API — فقط چیزی که برای عیب‌یابی لازم است
            Log::error('[SMS:kavenegar] ارسال ناموفق', [
                'phone'      => $this->mask($phoneNumber),
                'http'       => $response->status(),
                'api_status' => $status,
                'api_msg'    => $response->json('return.message'),
            ]);
            return false;
        }

        return true;
    }

    public function name(): string
    {
        return 'کاوه‌نگار';
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey) && filled($this->template);
    }

    private function mask(string $phone): string
    {
        if (strlen($phone) < 8) return '***';

        return substr($phone, 0, 4) . str_repeat('*', 3) . substr($phone, -4);
    }
}
