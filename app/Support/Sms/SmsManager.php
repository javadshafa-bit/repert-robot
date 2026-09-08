<?php

namespace App\Support\Sms;

use App\Models\Setting;

/**
 * انتخاب سرویس پیامک برای سازمان جاری.
 *
 * هر سازمان می‌تواند پنل پیامک خودش را وصل کند تا هزینه‌ی کد یک‌بارمصرفش
 * پای خودش باشد. اگر وصل نکند، به حساب سراسری نصب (اگر تنظیم شده باشد)
 * برمی‌گردد و در نهایت به درایور log.
 *
 * چون انتخاب به سازمان جاری وابسته است، این کلاس نباید singleton باشد؛
 * در وبهوک ربات، مستأجر تازه بعد از شروع درخواست مشخص می‌شود.
 */
class SmsManager
{
    /** منابع مجاز برای اعتبار پیامک */
    public const SOURCE_PLATFORM = 'platform';
    public const SOURCE_OWN      = 'own';

    public function forCurrentTenant(): SmsSender
    {
        return $this->source() === self::SOURCE_OWN
            ? $this->tenantSender()
            : $this->platformSender();
    }

    /** «پنل خودم» یا «پنل نصب» */
    public function source(): string
    {
        $source = Setting::get('sms_source', self::SOURCE_PLATFORM);

        return in_array($source, [self::SOURCE_PLATFORM, self::SOURCE_OWN], true)
            ? $source
            : self::SOURCE_PLATFORM;
    }

    /**
     * پنل خودِ سازمان.
     *
     * اگر اعتبارش ناقص باشد عمداً به حساب نصب برنمی‌گردیم — سازمانی که گفته
     * «از پنل خودم استفاده کن» نباید ناخواسته از اعتبار پلتفرم خرج کند.
     * KavenegarSms با اعتبار خالی، isConfigured=false می‌دهد و صفحه‌ی تنظیمات
     * همان را قرمز نشان می‌دهد.
     */
    private function tenantSender(): SmsSender
    {
        return new KavenegarSms(
            Setting::get('sms_api_key') ?: null,
            Setting::get('sms_template') ?: null,
            (int) config('sms.timeout', 10),
        );
    }

    /** حساب سراسری نصب، از env */
    private function platformSender(): SmsSender
    {
        return match (config('sms.driver')) {
            'kavenegar' => new KavenegarSms(
                config('sms.kavenegar.api_key'),
                config('sms.kavenegar.template'),
                (int) config('sms.timeout', 10),
            ),
            // درایور ناشناخته نباید بی‌صدا به ارسال واقعی برسد
            default => new LogSms(),
        };
    }
}
