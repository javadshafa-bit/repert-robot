<?php

namespace App\Providers;

use App\Support\Sms\SmsManager;
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
        // عمداً bind نه singleton: انتخاب سرویس به سازمان جاری وابسته است و
        // در وبهوک ربات، مستأجر بعد از شروع درخواست مشخص می‌شود.
        $this->app->bind(SmsSender::class, fn ($app) => $app->make(SmsManager::class)->forCurrentTenant());
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
