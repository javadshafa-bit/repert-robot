<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Support\Sms\SmsManager;
use App\Support\Sms\SmsSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            $tenant = self::webhookTenant($request);

            return Limit::perMinute(self::webhookLimitFor($tenant))->by(
                optional($tenant)->id ?? $request->ip()
            );
        });
    }

    /**
     * throttle در اولویت میدلورها پیش از SubstituteBindings اجرا می‌شود، پس این‌جا
     * پارامتر tenant هنوز همان رشته‌ی webhook_secret است نه مدل. بدون این تبدیل،
     * کلید عملاً IP بود و همه‌ی سازمان‌ها یک سقف مشترک داشتند.
     */
    private static function webhookTenant(Request $request): ?Tenant
    {
        $param = $request->route('tenant');

        if ($param instanceof Tenant) return $param;
        if (!is_string($param) || $param === '') return null;

        return Tenant::where('webhook_secret', $param)->first();
    }

    /**
     * سقف هر دقیقه‌ی وبهوک یک سازمان.
     *
     * ربات نظرسنجی مراسم در چند دقیقه‌ی بین‌پرده/پایان برنامه صدها نفر را هم‌زمان
     * می‌گیرد و هر نفر حدود ده آپدیت می‌فرستد (شروع، نام، شماره، هر سؤال)؛ سقف ۳۰۰
     * آن‌جا پیام‌ها را با 429 پس می‌زد. کوئری خام چون TenantContext هنوز ست نشده.
     */
    private static function webhookLimitFor(?Tenant $tenant): int
    {
        if (!$tenant) return 300;

        $survey = DB::table('settings')
            ->where('tenant_id', $tenant->id)
            ->where('key', 'bot_survey_mode')
            ->value('value');

        return $survey === '1' ? 3000 : 300;
    }
}
