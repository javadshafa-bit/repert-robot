<?php

namespace Tests\Feature;

use App\Models\DiscountCode;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * پرداخت زرین‌پال: محاسبه‌ی مبلغ، فعال‌سازی اشتراک، و قواعد امنیتی
 * (ایدمپوتنت بودن callback، دستکاری مبلغ، مصرف کد تخفیف).
 */
class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private const AUTHORITY = 'A00000000000000000000000000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::forget();
        PlatformSetting::forgetCache();
        PlatformSetting::set('zarinpal_merchant_id', str_repeat('a', 36));
        PlatformSetting::set('zarinpal_sandbox', '1');
        PlatformSetting::set('price_per_day', 5000);
        PlatformSetting::set('min_payment_amount', 50000);
    }

    protected function tearDown(): void
    {
        TenantContext::forget();
        PlatformSetting::forgetCache();
        parent::tearDown();
    }

    private function makeTenant(string $status = Tenant::STATUS_PENDING_PAYMENT, $endsAt = null): Tenant
    {
        return Tenant::create([
            'name'                 => 'سازمان تست',
            'owner_name'           => 'مدیر',
            'email'                => 'owner@example.com',
            'status'               => $status,
            'bot_token'            => 'TOKEN',
            'webhook_secret'       => Tenant::generateWebhookSecret(),
            'subscription_ends_at' => $endsAt,
        ]);
    }

    private function makeAdmin(Tenant $tenant): User
    {
        return User::create([
            'tenant_id'      => $tenant->id,
            'name'           => 'admin',
            'email'          => 'admin@example.com',
            'password'       => 'password123',
            'is_super_admin' => true,
        ]);
    }

    /** @param int|null $verifyAmount مبلغ ریالی که درگاه در verify برمی‌گرداند */
    private function fakeGateway(int $verifyCode = 100, ?int $verifyAmount = 1500000): void
    {
        Http::fake([
            '*/payment/request.json' => Http::response([
                'data'   => ['code' => 100, 'message' => 'Success', 'authority' => self::AUTHORITY, 'fee' => 0],
                'errors' => [],
            ], 200),
            '*/payment/verify.json' => Http::response([
                'data'   => ['code' => $verifyCode, 'ref_id' => 987654321, 'amount' => $verifyAmount],
                'errors' => [],
            ], 200),
            '*' => Http::response(['ok' => true, 'result' => ['username' => 'bot']], 200),
        ]);
    }

    // ── جریان موفق ──────────────────────────────────────────────────────────

    public function test_successful_payment_activates_subscription_and_bot(): void
    {
        $this->fakeGateway();

        $tenant = $this->makeTenant();
        $admin  = $this->makeAdmin($tenant);

        $this->actingAs($admin)
            ->post(route('billing.pay'), ['mode' => 'days', 'value' => 30])
            ->assertRedirect('https://sandbox.zarinpal.com/pg/StartPay/' . self::AUTHORITY);

        $payment = Payment::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(150000, $payment->amount);         // ۳۰ روز × ۵۰۰۰ تومان
        $this->assertSame(30, $payment->days_granted);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);

        $this->actingAs($admin)
            ->get(route('billing.callback', ['Authority' => self::AUTHORITY, 'Status' => 'OK']))
            ->assertOk()
            ->assertSee('987654321');

        $tenant->refresh();
        $this->assertSame(Tenant::STATUS_ACTIVE, $tenant->status);
        $this->assertTrue($tenant->hasActiveSubscription());
        $this->assertSame(30, $tenant->subscriptionDaysLeft());
        $this->assertTrue($tenant->botIsUsable());

        // مبلغ ارسالی به درگاه ریالی است (تومان × ۱۰)
        Http::assertSent(fn ($request) => str_contains($request->url(), 'request.json')
            && $request['amount'] === 1500000
            && $request['currency'] === 'IRR');

        // ربات بدون وارد کردن دوباره‌ی توکن برمی‌گردد
        Http::assertSent(fn ($request) => str_contains($request->url(), 'setWebhook'));
    }

    /** تمدید بعد از انقضا: روزها به «امروز» اضافه می‌شود نه به تاریخ گذشته */
    public function test_renewal_after_expiry_restores_the_bot(): void
    {
        $this->fakeGateway();

        $tenant = $this->makeTenant(Tenant::STATUS_EXPIRED, now()->subDays(20));
        $admin  = $this->makeAdmin($tenant);

        $this->actingAs($admin)->post(route('billing.pay'), ['mode' => 'days', 'value' => 30]);
        $this->actingAs($admin)->get(route('billing.callback', ['Authority' => self::AUTHORITY, 'Status' => 'OK']));

        $tenant->refresh();
        $this->assertSame(Tenant::STATUS_ACTIVE, $tenant->status);
        $this->assertSame(30, $tenant->subscriptionDaysLeft());
        $this->assertSame('TOKEN', $tenant->bot_token);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'setWebhook'));
    }

    /** تمدید زودهنگام نباید روزهای باقیمانده را بسوزاند */
    public function test_early_renewal_adds_to_remaining_days(): void
    {
        $this->fakeGateway();

        $tenant = $this->makeTenant(Tenant::STATUS_ACTIVE, now()->addDays(10));
        $admin  = $this->makeAdmin($tenant);

        $this->actingAs($admin)->post(route('billing.pay'), ['mode' => 'days', 'value' => 30]);
        $this->actingAs($admin)->get(route('billing.callback', ['Authority' => self::AUTHORITY, 'Status' => 'OK']));

        $this->assertSame(40, $tenant->fresh()->subscriptionDaysLeft());
    }

    // ── قواعد امنیتی ────────────────────────────────────────────────────────

    /** باز کردن دوباره‌ی آدرس بازگشت نباید اشتراک را دو بار اضافه کند */
    public function test_callback_is_idempotent(): void
    {
        $this->fakeGateway();

        $tenant = $this->makeTenant();
        $admin  = $this->makeAdmin($tenant);

        $this->actingAs($admin)->post(route('billing.pay'), ['mode' => 'days', 'value' => 30]);

        $this->actingAs($admin)->get(route('billing.callback', ['Authority' => self::AUTHORITY, 'Status' => 'OK']));
        $firstEndsAt = $tenant->fresh()->subscription_ends_at;

        $this->actingAs($admin)->get(route('billing.callback', ['Authority' => self::AUTHORITY, 'Status' => 'OK']));

        $this->assertEquals($firstEndsAt, $tenant->fresh()->subscription_ends_at);
        $this->assertSame(1, Payment::withoutGlobalScopes()->where('status', Payment::STATUS_PAID)->count());
    }

    /** اگر درگاه مبلغ دیگری برگرداند، پرداخت ناموفق است و اشتراک فعال نمی‌شود */
    public function test_amount_mismatch_fails_the_payment(): void
    {
        $this->fakeGateway(100, 90000);   // ۹۰۰۰ تومان به‌جای ۱۵۰۰۰۰

        $tenant = $this->makeTenant();
        $admin  = $this->makeAdmin($tenant);

        $this->actingAs($admin)->post(route('billing.pay'), ['mode' => 'days', 'value' => 30]);
        $this->actingAs($admin)->get(route('billing.callback', ['Authority' => self::AUTHORITY, 'Status' => 'OK']))->assertOk();

        $this->assertSame(Payment::STATUS_FAILED, Payment::withoutGlobalScopes()->firstOrFail()->status);
        $this->assertFalse($tenant->fresh()->hasActiveSubscription());
    }

    /** Status=NOK یعنی کاربر پرداخت را لغو کرده؛ verify اصلاً صدا زده نمی‌شود */
    public function test_canceled_payment_does_not_activate_subscription(): void
    {
        $this->fakeGateway();

        $tenant = $this->makeTenant();
        $admin  = $this->makeAdmin($tenant);

        $this->actingAs($admin)->post(route('billing.pay'), ['mode' => 'days', 'value' => 30]);
        $this->actingAs($admin)->get(route('billing.callback', ['Authority' => self::AUTHORITY, 'Status' => 'NOK']))->assertOk();

        $this->assertSame(Payment::STATUS_CANCELED, Payment::withoutGlobalScopes()->firstOrFail()->status);
        $this->assertFalse($tenant->fresh()->hasActiveSubscription());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'verify.json'));
    }

    /** verify ناموفق (کد غیر ۱۰۰/۱۰۱) نباید اشتراک بدهد */
    public function test_failed_verification_does_not_activate_subscription(): void
    {
        $this->fakeGateway(-51, null);

        $tenant = $this->makeTenant();
        $admin  = $this->makeAdmin($tenant);

        $this->actingAs($admin)->post(route('billing.pay'), ['mode' => 'days', 'value' => 30]);
        $this->actingAs($admin)->get(route('billing.callback', ['Authority' => self::AUTHORITY, 'Status' => 'OK']))->assertOk();

        $this->assertSame(Payment::STATUS_FAILED, Payment::withoutGlobalScopes()->firstOrFail()->status);
        $this->assertFalse($tenant->fresh()->hasActiveSubscription());
    }

    /** merchant_id نباید در دیتابیس ذخیره شود */
    public function test_merchant_id_is_never_stored_in_raw_response(): void
    {
        $this->fakeGateway();

        $tenant = $this->makeTenant();
        $admin  = $this->makeAdmin($tenant);

        $this->actingAs($admin)->post(route('billing.pay'), ['mode' => 'days', 'value' => 30]);
        $this->actingAs($admin)->get(route('billing.callback', ['Authority' => self::AUTHORITY, 'Status' => 'OK']));

        $raw = json_encode(Payment::withoutGlobalScopes()->firstOrFail()->raw_response);
        $this->assertStringNotContainsString(str_repeat('a', 36), $raw);
    }

    // ── کد تخفیف ────────────────────────────────────────────────────────────

    public function test_discount_reduces_amount_but_not_days(): void
    {
        $this->fakeGateway(100, 1200000);

        $tenant = $this->makeTenant();
        $admin  = $this->makeAdmin($tenant);

        DiscountCode::create(['code' => 'OFF20', 'percent' => 20, 'is_active' => true]);

        $this->actingAs($admin)
            ->postJson(route('billing.quote'), ['mode' => 'days', 'value' => 30, 'discount_code' => 'off20'])
            ->assertOk()
            ->assertJson([
                'original_amount' => 150000,
                'discount_amount' => 30000,
                'amount'          => 120000,
                'days'            => 30,
            ]);

        $this->actingAs($admin)->post(route('billing.pay'), ['mode' => 'days', 'value' => 30, 'discount_code' => 'OFF20']);

        // تا پیش از پرداخت موفق، کد مصرف نشده است
        $this->assertSame(0, DiscountCode::firstOrFail()->used_count);

        $this->actingAs($admin)->get(route('billing.callback', ['Authority' => self::AUTHORITY, 'Status' => 'OK']));

        $this->assertSame(1, DiscountCode::firstOrFail()->used_count);
        $this->assertSame(30, $tenant->fresh()->subscriptionDaysLeft());
    }

    public function test_failed_payment_does_not_consume_discount(): void
    {
        $this->fakeGateway(-51, null);

        $tenant = $this->makeTenant();
        $admin  = $this->makeAdmin($tenant);

        DiscountCode::create(['code' => 'OFF20', 'percent' => 20, 'is_active' => true]);

        $this->actingAs($admin)->post(route('billing.pay'), ['mode' => 'days', 'value' => 30, 'discount_code' => 'OFF20']);
        $this->actingAs($admin)->get(route('billing.callback', ['Authority' => self::AUTHORITY, 'Status' => 'OK']));

        $this->assertSame(0, DiscountCode::firstOrFail()->used_count);
    }

    public function test_each_invalid_discount_state_has_its_own_message(): void
    {
        $tenant = $this->makeTenant();
        $admin  = $this->makeAdmin($tenant);

        $cases = [
            ['code' => 'INACTIVE', 'attrs' => ['is_active' => false], 'expect' => 'غیرفعال'],
            ['code' => 'EXPIRED',  'attrs' => ['expires_at' => now()->subDay()], 'expect' => 'مهلت'],
            ['code' => 'FUTURE',   'attrs' => ['starts_at' => now()->addWeek()], 'expect' => 'شروع نشده'],
            ['code' => 'FULL',     'attrs' => ['max_uses' => 2, 'used_count' => 2], 'expect' => 'ظرفیت'],
        ];

        foreach ($cases as $case) {
            DiscountCode::create(array_merge(
                ['code' => $case['code'], 'percent' => 20, 'is_active' => true],
                $case['attrs']
            ));

            $response = $this->actingAs($admin)->postJson(route('billing.quote'), [
                'mode' => 'days', 'value' => 30, 'discount_code' => $case['code'],
            ])->assertOk();

            $this->assertStringContainsString(
                $case['expect'],
                implode(' ', $response->json('errors')),
                "پیام خطای مشخصی برای {$case['code']} برنگشت."
            );

            $this->assertSame(0, $response->json('discount_amount'));
        }

        // کد ناموجود
        $response = $this->actingAs($admin)->postJson(route('billing.quote'), [
            'mode' => 'days', 'value' => 30, 'discount_code' => 'NOPE',
        ])->assertOk();

        $this->assertStringContainsString('وجود ندارد', implode(' ', $response->json('errors')));
    }

    // ── اعتبارسنجی مبلغ ─────────────────────────────────────────────────────

    public function test_amount_below_minimum_is_rejected(): void
    {
        $tenant = $this->makeTenant();
        $admin  = $this->makeAdmin($tenant);

        $this->actingAs($admin)
            ->post(route('billing.pay'), ['mode' => 'amount', 'value' => 10000])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Payment::withoutGlobalScopes()->count());
    }

    public function test_amount_mode_converts_money_to_days(): void
    {
        $tenant = $this->makeTenant();
        $admin  = $this->makeAdmin($tenant);

        $this->actingAs($admin)
            ->postJson(route('billing.quote'), ['mode' => 'amount', 'value' => 123000])
            ->assertOk()
            ->assertJson(['days' => 24, 'amount' => 123000]);   // floor(123000 / 5000)
    }
}
