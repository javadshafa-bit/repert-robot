<?php

namespace App\Support\Sms;

use Illuminate\Support\Facades\Log;

/**
 * درایور توسعه: پیامکی ارسال نمی‌شود و کد در لاگ می‌نشیند.
 *
 * این تنها جایی در کل سیستم است که کد خام نوشته می‌شود — عمدی و فقط برای
 * توسعه. سطح warning انتخاب شده تا در لاگ production جلب توجه کند.
 */
class LogSms implements SmsSender
{
    public function sendOtp(string $phoneNumber, string $code): bool
    {
        Log::warning('[SMS:log] کد یک‌بارمصرف ارسال نشد — درایور log فعال است', [
            'phone' => $this->mask($phoneNumber),
            'code'  => $code,
        ]);

        return true;
    }

    public function name(): string
    {
        return 'log (بدون ارسال واقعی)';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    /** ۰۹۱۲***۴۵۶۷ — حتی در لاگ توسعه، شماره‌ی کامل لازم نیست */
    private function mask(string $phone): string
    {
        if (strlen($phone) < 8) return '***';

        return substr($phone, 0, 4) . str_repeat('*', 3) . substr($phone, -4);
    }
}
