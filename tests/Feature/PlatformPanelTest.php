<?php

namespace Tests\Feature;

use App\Models\DiscountCode;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\JalaliDate;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * پنل سوپرادمین: کد تخفیف، تنظیمات پلتفرم، و مرزهای دسترسی.
 */
class PlatformPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $platformAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::forget();
        PlatformSetting::forgetCache();

        $this->platformAdmin = User::create([
            'name'              => 'platform',
            'email'             => 'platform@example.com',
            'password'          => 'password123',
            'is_platform_admin' => true,
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::forget();
        PlatformSetting::forgetCache();
        parent::tearDown();
    }

    private function makeTenantAdmin(): User
    {
        $tenant = Tenant::create([
            'name'                 => 'سازمان',
            'owner_name'           => 'مدیر',
            'email'                => 'org@example.com',
            'status'               => Tenant::STATUS_ACTIVE,
            'webhook_secret'       => Tenant::generateWebhookSecret(),
            'subscription_ends_at' => now()->addMonth(),
        ]);

        return User::create([
            'tenant_id'      => $tenant->id,
            'name'           => 'admin',
            'email'          => 'admin@example.com',
            'password'       => 'password123',
            'is_super_admin' => true,
        ]);
    }

    // ── کد تخفیف ────────────────────────────────────────────────────────────

    public function test_platform_admin_can_create_a_discount_code(): void
    {
        $this->actingAs($this->platformAdmin)
            ->post(route('platform.discount-codes.store'), [
                'code'       => ' nowruz1405 ',
                'percent'    => 25,
                'max_uses'   => 10,
                'starts_at'  => '1405/01/01',
                'expires_at' => '1405/01/15',
                'is_active'  => 1,
            ])
            ->assertRedirect(route('platform.discount-codes.index'));

        $code = DiscountCode::firstOrFail();

        $this->assertSame('NOWRUZ1405', $code->code, 'کد باید نرمال‌سازی شود.');
        $this->assertSame(25, $code->percent);
        $this->assertSame('1405/01/01', JalaliDate::format($code->starts_at));
        $this->assertSame('1405/01/15', JalaliDate::format($code->expires_at));
        // پایان اعتبار باید تا آخر آن روز باشد، نه ابتدای آن
        $this->assertTrue($code->expires_at->greaterThan($code->starts_at->copy()->addDays(14)));
    }

    public function test_invalid_jalali_date_is_rejected_with_a_clear_message(): void
    {
        $this->actingAs($this->platformAdmin)
            ->post(route('platform.discount-codes.store'), [
                'code' => 'TEST', 'percent' => 10, 'expires_at' => '1405/13/40',
            ])
            ->assertSessionHasErrors('expires_at');

        $this->assertSame(0, DiscountCode::count());
    }

    public function test_duplicate_code_is_rejected(): void
    {
        DiscountCode::create(['code' => 'SAME', 'percent' => 10, 'is_active' => true]);

        $this->actingAs($this->platformAdmin)
            ->post(route('platform.discount-codes.store'), ['code' => 'same', 'percent' => 20])
            ->assertSessionHasErrors('code');
    }

    public function test_used_code_cannot_be_deleted_but_can_be_disabled(): void
    {
        $code = DiscountCode::create(['code' => 'USED', 'percent' => 10, 'is_active' => true, 'used_count' => 3]);

        $this->actingAs($this->platformAdmin)
            ->delete(route('platform.discount-codes.destroy', $code))
            ->assertRedirect();

        $this->assertDatabaseHas('discount_codes', ['id' => $code->id]);

        $this->actingAs($this->platformAdmin)
            ->patch(route('platform.discount-codes.toggle', $code))
            ->assertRedirect();

        $this->assertFalse($code->fresh()->is_active);
    }

    public function test_max_uses_cannot_drop_below_used_count(): void
    {
        $code = DiscountCode::create(['code' => 'USED', 'percent' => 10, 'is_active' => true, 'used_count' => 5]);

        $this->actingAs($this->platformAdmin)
            ->put(route('platform.discount-codes.update', $code), ['code' => 'USED', 'percent' => 10, 'max_uses' => 2])
            ->assertSessionHasErrors('max_uses');
    }

    public function test_unused_code_can_be_deleted(): void
    {
        $code = DiscountCode::create(['code' => 'FRESH', 'percent' => 10, 'is_active' => true]);

        $this->actingAs($this->platformAdmin)
            ->delete(route('platform.discount-codes.destroy', $code))
            ->assertRedirect(route('platform.discount-codes.index'));

        $this->assertSame(0, DiscountCode::count());
    }

    // ── تنظیمات پلتفرم ──────────────────────────────────────────────────────

    public function test_platform_settings_can_be_updated(): void
    {
        $this->actingAs($this->platformAdmin)
            ->post(route('platform.settings.update'), [
                'price_per_day'        => 8000,
                'min_payment_amount'   => 80000,
                'zarinpal_merchant_id' => str_repeat('b', 36),
                'zarinpal_sandbox'     => 1,
            ])
            ->assertRedirect();

        PlatformSetting::forgetCache();

        $this->assertSame(8000, PlatformSetting::int('price_per_day'));
        $this->assertSame(80000, PlatformSetting::int('min_payment_amount'));
        $this->assertTrue(PlatformSetting::bool('zarinpal_sandbox'));
    }

    /** تنظیمات پلتفرم نباید در جدول per-tenant «settings» بنشیند */
    public function test_platform_settings_do_not_leak_into_tenant_settings_table(): void
    {
        $this->actingAs($this->platformAdmin)->post(route('platform.settings.update'), [
            'price_per_day' => 9000, 'min_payment_amount' => 90000,
        ]);

        $this->assertDatabaseMissing('settings', ['key' => 'price_per_day']);
        $this->assertDatabaseHas('platform_settings', ['key' => 'price_per_day', 'value' => '9000']);
    }

    // ── مرزها ───────────────────────────────────────────────────────────────

    public function test_tenant_admin_cannot_touch_platform_routes(): void
    {
        $admin = $this->makeTenantAdmin();

        foreach ([
            route('platform.dashboard'),
            route('platform.discount-codes.index'),
            route('platform.settings.edit'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertRedirect(route('admin.dashboard'));
        }
    }

    /** هیچ مسیری در ناحیه‌ی platform نباید ربات وصل کند */
    public function test_platform_area_has_no_bot_connection_route(): void
    {
        $routes = collect(app('router')->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->getName() ?? '', 'platform.'))
            ->map(fn ($route) => $route->getActionName());

        $this->assertNotEmpty($routes);

        foreach ($routes as $action) {
            $this->assertStringNotContainsString('connect', strtolower($action));
            $this->assertStringNotContainsString('webhook', strtolower($action));
        }
    }
}
