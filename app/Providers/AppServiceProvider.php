<?php

namespace App\Providers;

use App\Support\Sms\KavenegarSms;
use App\Support\Sms\LogSms;
use App\Support\Sms\SmsSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SmsSender::class, function () {
            // درایور ناشناخته نباید بی‌صدا به ارسال واقعی برسد؛ به log می‌افتد
            return match (config('sms.driver')) {
                'kavenegar' => new KavenegarSms(
                    config('sms.kavenegar.api_key'),
                    config('sms.kavenegar.template'),
                    (int) config('sms.timeout', 10),
                ),
                default => new LogSms(),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiters();
    }

    /**
     * سقف درخواست وبهوک ربات.
     *
     * کلید عمداً مستأجر است نه IP: همه‌ی درخواست‌های بله از چند IP محدود می‌آید، پس
     * کلیدِ IP یعنی یک سقف مشترک برای همه‌ی ربات‌های این نصب — پرترافیک‌ترین سازمان
     * بقیه را خفه می‌کند. روی مسیر سازگاری قدیمی (بدون پارامتر tenant) به IP برمی‌گردیم.
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for('bot-webhook', function (Request $request) {
            return Limit::perMinute(300)->by(
                optional($request->route('tenant'))->id ?? $request->ip()
            );
        });
    }
}
