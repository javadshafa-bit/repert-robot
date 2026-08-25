<?php

namespace Tests\Feature;

use App\Models\BotState;
use App\Models\SubscriptionLog;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * کرون انقضا: چه کسی خاموش می‌شود، چه چیزی دست‌نخورده می‌ماند.
 */
class SubscriptionExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::forget();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['username' => 'bot']], 200)]);
    }

    protected function tearDown(): void
    {
        TenantContext::forget();
        parent::tearDown();
    }

    private function makeTenant(string $name, string $status, $endsAt, bool $unlimited = false): Tenant
    {
        return Tenant::create([
            'name'                 => $name,
            'owner_name'           => $name,
            'email'                => $name . '@example.com',
            'status'               => $status,
            'bot_token'            => 'TOKEN-' . $name,
            'bot_connected_at'     => now(),
            'webhook_secret'       => Tenant::generateWebhookSecret(),
            'subscription_ends_at' => $endsAt,
            'is_unlimited'         => $unlimited,
        ]);
    }

    public function test_expired_tenant_is_deactivated_but_keeps_its_token(): void
    {
        $tenant = $this->makeTenant('A', Tenant::STATUS_ACTIVE, now()->subHour());

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $tenant->refresh();

        $this->assertSame(Tenant::STATUS_EXPIRED, $tenant->status);
        $this->assertSame('TOKEN-A', $tenant->bot_token, 'توکن ربات نباید پاک شود.');
        $this->assertFalse($tenant->botIsUsable());

        Http::assertSent(fn ($request) => str_contains($request->url(), 'deleteWebhook')
            && str_contains($request->url(), 'TOKEN-A'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'setWebhook'));
    }

    public function test_expiry_is_written_to_the_subscription_log(): void
    {
        $tenant = $this->makeTenant('A', Tenant::STATUS_ACTIVE, now()->subHour());

        $this->artisan('subscriptions:expire');

        $log = TenantContext::forTenant($tenant, fn () => SubscriptionLog::latest('id')->firstOrFail());
        TenantContext::forget();

        $this->assertSame(SubscriptionLog::SOURCE_EXPIRE, $log->source);
        $this->assertSame(Tenant::STATUS_ACTIVE, $log->from_status);
        $this->assertSame(Tenant::STATUS_EXPIRED, $log->to_status);
        $this->assertNull($log->user_id, 'کرون کاربر ندارد.');
    }

    public function test_webhook_of_an_expired_tenant_is_ignored(): void
    {
        $tenant = $this->makeTenant('A', Tenant::STATUS_ACTIVE, now()->subHour());

        $this->artisan('subscriptions:expire');

        $this->postJson('/api/bot/webhook/' . $tenant->webhook_secret, [
            'message' => ['chat' => ['id' => '999'], 'text' => '/start'],
        ])->assertOk();

        $this->assertSame(0, BotState::withoutGlobalScopes()->count());
    }

    public function test_unlimited_and_valid_tenants_are_untouched(): void
    {
        $unlimited = $this->makeTenant('U', Tenant::STATUS_ACTIVE, null, true);
        $valid     = $this->makeTenant('V', Tenant::STATUS_ACTIVE, now()->addDays(3));

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertSame(Tenant::STATUS_ACTIVE, $unlimited->fresh()->status);
        $this->assertSame(Tenant::STATUS_ACTIVE, $valid->fresh()->status);
        Http::assertNothingSent();
    }

    /** سازمان معلق را دست نمی‌زند (وضعیتش تصمیم سوپرادمین است) */
    public function test_suspended_tenant_is_left_alone(): void
    {
        $tenant = $this->makeTenant('S', Tenant::STATUS_SUSPENDED, now()->subDay());

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertSame(Tenant::STATUS_SUSPENDED, $tenant->fresh()->status);
    }

    public function test_command_reports_long_expired_tenants_without_deleting_them(): void
    {
        $old = $this->makeTenant('OLD', Tenant::STATUS_EXPIRED, now()->subMonths(8));

        $this->artisan('subscriptions:expire')
            ->expectsOutputToContain('OLD')
            ->assertSuccessful();

        // داده حذف نمی‌شود، فقط گزارش می‌شود
        $this->assertDatabaseHas('tenants', ['id' => $old->id]);
    }

    /** اجرای دوباره‌ی کرون نباید چیزی را دوباره تغییر دهد */
    public function test_running_twice_is_harmless(): void
    {
        $this->makeTenant('A', Tenant::STATUS_ACTIVE, now()->subHour());

        $this->artisan('subscriptions:expire');
        $logsAfterFirst = SubscriptionLog::withoutGlobalScopes()->count();

        $this->artisan('subscriptions:expire');

        $this->assertSame($logsAfterFirst, SubscriptionLog::withoutGlobalScopes()->count());
    }
}
