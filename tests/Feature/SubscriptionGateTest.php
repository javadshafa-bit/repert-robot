<?php

namespace Tests\Feature;

use App\Models\BotState;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * گیت اشتراک: چه کسی وارد پنل می‌شود، چه کسی به /billing می‌رود، و ربات چه وقت می‌خوابد.
 */
class SubscriptionGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::forget();
    }

    protected function tearDown(): void
    {
        TenantContext::forget();
        parent::tearDown();
    }

    private function makeTenant(string $status, ?string $endsAt = null, bool $unlimited = false): Tenant
    {
        return Tenant::create([
            'name'                 => 'سازمان ' . $status,
            'owner_name'           => 'مدیر',
            'email'                => $status . '@example.com',
            'status'               => $status,
            'bot_token'            => 'TOKEN-' . $status,
            'webhook_secret'       => Tenant::generateWebhookSecret(),
            'subscription_ends_at' => $endsAt,
            'is_unlimited'         => $unlimited,
        ]);
    }

    private function makeAdmin(Tenant $tenant): User
    {
        return User::create([
            'tenant_id'      => $tenant->id,
            'name'           => 'admin',
            'email'          => 'admin' . $tenant->id . '@example.com',
            'password'       => 'password123',
            'is_super_admin' => true,
        ]);
    }

    public function test_tenant_with_active_subscription_reaches_the_panel(): void
    {
        $tenant = $this->makeTenant(Tenant::STATUS_ACTIVE, now()->addDays(10));

        $this->actingAs($this->makeAdmin($tenant))->get('/admin')->assertOk();
    }

    public function test_unlimited_tenant_reaches_the_panel_without_end_date(): void
    {
        $tenant = $this->makeTenant(Tenant::STATUS_ACTIVE, null, true);

        $this->actingAs($this->makeAdmin($tenant))->get('/admin')->assertOk();
    }

    public function test_unpaid_tenant_is_redirected_to_billing(): void
    {
        $tenant = $this->makeTenant(Tenant::STATUS_PENDING_PAYMENT);
        $admin  = $this->makeAdmin($tenant);

        $this->actingAs($admin)->get('/admin')->assertRedirect(route('billing.index'));
        $this->actingAs($admin)->get('/admin/reports')->assertRedirect(route('billing.index'));

        // ولی خود صفحه‌ی پرداخت باز است
        $this->actingAs($admin)->get(route('billing.index'))->assertOk();
    }

    /** اشتراکی که بین دو اجرای کرون تمام شده، همان لحظه‌ی ورود هم‌گام می‌شود */
    public function test_stale_active_status_is_refreshed_on_request(): void
    {
        $tenant = $this->makeTenant(Tenant::STATUS_ACTIVE, now()->subDay());
        $admin  = $this->makeAdmin($tenant);

        $this->actingAs($admin)->get('/admin')->assertRedirect(route('billing.index'));

        $this->assertSame(Tenant::STATUS_EXPIRED, $tenant->fresh()->status);
    }

    public function test_expired_tenant_can_still_log_in_and_see_billing(): void
    {
        $tenant = $this->makeTenant(Tenant::STATUS_EXPIRED, now()->subDay());
        $admin  = $this->makeAdmin($tenant);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password123'])
            ->assertRedirect(route('billing.index'));

        $this->assertAuthenticated();
    }

    public function test_suspended_tenant_user_is_still_blocked_at_login(): void
    {
        $tenant = $this->makeTenant(Tenant::STATUS_SUSPENDED, now()->addYear());
        $admin  = $this->makeAdmin($tenant);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    // ── ربات ────────────────────────────────────────────────────────────────

    public function test_bot_stops_working_when_subscription_expires(): void
    {
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);

        $tenant = $this->makeTenant(Tenant::STATUS_EXPIRED, now()->subDay());

        $this->postJson('/api/bot/webhook/' . $tenant->webhook_secret, [
            'message' => ['chat' => ['id' => '999'], 'text' => '/start'],
        ])->assertOk();

        $this->assertSame(0, BotState::withoutGlobalScopes()->count());
    }

    /** حتی اگر ستون status هنوز active مانده باشد، تاریخ اشتراک حرف آخر را می‌زند */
    public function test_bot_stops_even_when_status_column_is_stale(): void
    {
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);

        $tenant = $this->makeTenant(Tenant::STATUS_ACTIVE, now()->subDay());

        $this->postJson('/api/bot/webhook/' . $tenant->webhook_secret, [
            'message' => ['chat' => ['id' => '999'], 'text' => '/start'],
        ])->assertOk();

        $this->assertSame(0, BotState::withoutGlobalScopes()->count());
    }

    // ── مرزهای پنل ──────────────────────────────────────────────────────────

    public function test_platform_admin_cannot_enter_tenant_panel(): void
    {
        $platformAdmin = User::create([
            'name'              => 'platform',
            'email'             => 'platform@example.com',
            'password'          => 'password123',
            'is_platform_admin' => true,
        ]);

        $this->actingAs($platformAdmin)->get('/admin')->assertRedirect(route('platform.dashboard'));
        $this->actingAs($platformAdmin)->get('/admin/reports')->assertRedirect(route('platform.dashboard'));
    }

    public function test_tenant_admin_cannot_enter_platform_panel(): void
    {
        $tenant = $this->makeTenant(Tenant::STATUS_ACTIVE, now()->addMonth());

        $this->actingAs($this->makeAdmin($tenant))
            ->get('/platform/tenants')
            ->assertRedirect(route('admin.dashboard'));
    }

    /** هیچ صفحه‌ی پلتفرمی نباید توکن ربات را چاپ کند */
    public function test_platform_pages_never_print_bot_token(): void
    {
        $platformAdmin = User::create([
            'name'              => 'platform',
            'email'             => 'platform@example.com',
            'password'          => 'password123',
            'is_platform_admin' => true,
        ]);

        $tenant = $this->makeTenant(Tenant::STATUS_ACTIVE, now()->addMonth());

        $this->actingAs($platformAdmin)->get(route('platform.tenants.index'))
            ->assertOk()->assertDontSee($tenant->bot_token);

        $this->actingAs($platformAdmin)->get(route('platform.tenants.show', $tenant))
            ->assertOk()->assertDontSee($tenant->bot_token);

        $this->actingAs($platformAdmin)->get(route('platform.dashboard'))
            ->assertOk()->assertDontSee($tenant->bot_token);
    }
}
