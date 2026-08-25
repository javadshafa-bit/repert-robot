<?php

namespace Tests\Feature;

use App\Models\BotState;
use App\Models\Category;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * تست‌های بخش «الف» — ایرادهای بازبینی فاز ۱.
 * هر تست به یکی از بندهای الف-۱ تا الف-۱۰ گره خورده است.
 */
class Phase1FixesTest extends TestCase
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

    private function makeTenant(string $name, string $status = Tenant::STATUS_ACTIVE, ?string $token = 'TOKEN'): Tenant
    {
        return Tenant::create([
            'name'                 => $name,
            'owner_name'           => $name,
            'email'                => $name . '@example.com',
            'status'               => $status,
            'bot_token'            => $token,
            'webhook_secret'       => Tenant::generateWebhookSecret(),
            'subscription_ends_at' => $status === Tenant::STATUS_ACTIVE ? now()->addMonth() : null,
        ]);
    }

    // ── الف-۱ ────────────────────────────────────────────────────────────────

    /** هر دو شکل مسیر وبهوک باید از CSRF مستثنا باشد، وگرنه مسیر قدیمی ۴۱۹ می‌گیرد */
    public function test_both_webhook_paths_are_excluded_from_csrf(): void
    {
        $excluded = app(PreventRequestForgery::class)->getExcludedPaths();

        $this->assertContains('api/bot/webhook', $excluded);
        $this->assertContains('api/bot/webhook/*', $excluded);
    }

    /** مسیر قدیمی باید کار کند و آپدیت را به سازمان پیش‌فرض (کم‌ترین id فعال دارای توکن) بدهد */
    public function test_legacy_webhook_path_routes_to_default_tenant(): void
    {
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);

        $first  = $this->makeTenant('A');
        $second = $this->makeTenant('B');

        $this->postJson('/api/bot/webhook', [
            'message' => ['chat' => ['id' => '999'], 'text' => '/start'],
        ])->assertOk();

        $this->assertSame(1, BotState::withoutGlobalScopes()->where('tenant_id', $first->id)->count());
        $this->assertSame(0, BotState::withoutGlobalScopes()->where('tenant_id', $second->id)->count());
    }

    /** سازمان معلق یا بدون توکن نباید مالک مسیر قدیمی شود */
    public function test_legacy_webhook_skips_suspended_and_tokenless_tenants(): void
    {
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);

        $this->makeTenant('A', Tenant::STATUS_SUSPENDED);
        $this->makeTenant('B', Tenant::STATUS_ACTIVE, null);
        $usable = $this->makeTenant('C');

        $this->postJson('/api/bot/webhook', [
            'message' => ['chat' => ['id' => '999'], 'text' => '/start'],
        ])->assertOk();

        $this->assertSame(1, BotState::withoutGlobalScopes()->where('tenant_id', $usable->id)->count());
    }

    public function test_legacy_webhook_returns_404_when_no_tenant_is_connected(): void
    {
        $this->makeTenant('A', Tenant::STATUS_ACTIVE, null);

        $this->postJson('/api/bot/webhook', [
            'message' => ['chat' => ['id' => '999'], 'text' => '/start'],
        ])->assertNotFound();
    }

    // ── الف-۲ ────────────────────────────────────────────────────────────────

    /** با APP_URL لوکال، دستور باید متوقف شود و هیچ درخواستی به بله نرود */
    public function test_refresh_webhook_aborts_on_local_app_url(): void
    {
        Http::fake();
        config(['app.url' => 'http://localhost:8000']);

        $this->makeTenant('A');

        $this->artisan('tenants:refresh-webhook')->assertFailed();

        Http::assertNothingSent();
    }

    public function test_refresh_webhook_aborts_on_non_https_app_url(): void
    {
        Http::fake();
        config(['app.url' => 'http://panel.example.com']);

        $this->makeTenant('A');

        $this->artisan('tenants:refresh-webhook')->assertFailed();

        Http::assertNothingSent();
    }

    /** اگر آدرسِ ثبت‌شده روی بله با چیزی که فرستادیم فرق کند، دستور باید شکست را گزارش کند */
    public function test_refresh_webhook_fails_when_registered_url_differs(): void
    {
        config(['app.url' => 'https://panel.example.com']);

        Http::fake([
            '*/setWebhook'     => Http::response(['ok' => true], 200),
            '*/getMe'          => Http::response(['ok' => true, 'result' => ['username' => 'bot']], 200),
            '*/getWebhookInfo' => Http::response(['ok' => true, 'result' => ['url' => 'https://old.example.com/api/bot/webhook']], 200),
        ]);

        $this->makeTenant('A');

        $this->artisan('tenants:refresh-webhook')->assertFailed();
    }

    public function test_refresh_webhook_succeeds_when_registered_url_matches(): void
    {
        config(['app.url' => 'https://panel.example.com']);

        $tenant = $this->makeTenant('A');
        $url    = $tenant->webhookUrl();

        Http::fake([
            '*/setWebhook'     => Http::response(['ok' => true], 200),
            '*/getMe'          => Http::response(['ok' => true, 'result' => ['username' => 'bot']], 200),
            '*/getWebhookInfo' => Http::response(['ok' => true, 'result' => ['url' => $url]], 200),
        ]);

        $this->artisan('tenants:refresh-webhook')->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'setWebhook')
            && ($request['url'] ?? null) === $url);
    }

    // ── الف-۳ ────────────────────────────────────────────────────────────────

    /** limiter نام‌دار باید تعریف شده باشد، وگرنه مسیر وبهوک ۵۰۰ می‌دهد */
    public function test_bot_webhook_rate_limiter_is_registered(): void
    {
        $this->assertNotNull(
            app(\Illuminate\Cache\RateLimiter::class)->limiter('bot-webhook'),
            'limiter «bot-webhook» تعریف نشده است.'
        );
    }

    // ── الف-۷ ────────────────────────────────────────────────────────────────

    /** غیرفعال شدن سازمان نباید توکن ربات را برای همیشه پاک کند */
    public function test_suspending_tenant_keeps_bot_token(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $platformAdmin = User::create([
            'name'              => 'platform',
            'email'             => 'platform@example.com',
            'password'          => 'password123',
            'is_platform_admin' => true,
        ]);

        $tenant = $this->makeTenant('A', Tenant::STATUS_ACTIVE, 'KEEP-ME');

        $this->actingAs($platformAdmin)
            ->post(route('platform.tenants.suspend', $tenant), ['reason' => 'تست'])
            ->assertRedirect();

        $this->assertSame('KEEP-ME', $tenant->fresh()->bot_token);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'deleteWebhook'));
    }

    // ── الف-۹ ────────────────────────────────────────────────────────────────

    /** withoutScope فقط برای خواندن است؛ ساخت رکورد بدون tenant_id باید خطا بدهد */
    public function test_creating_record_inside_without_scope_throws(): void
    {
        $this->expectException(RuntimeException::class);

        TenantContext::withoutScope(function () {
            Category::create(['name' => 'بی‌صاحب', 'sort_order' => 1, 'is_active' => true]);
        });
    }

    /**
     * ولی با tenant_id صریح مجاز است — راه فرارِ اسکریپت‌های سطح پلتفرم.
     * (tenant_id عمداً fillable نیست، پس باید مستقیم روی مدل ست شود، نه با create().)
     */
    public function test_creating_record_with_explicit_tenant_id_is_allowed(): void
    {
        $tenant = $this->makeTenant('A');

        $category = TenantContext::withoutScope(function () use ($tenant) {
            $category            = new Category(['name' => 'دسته', 'sort_order' => 1, 'is_active' => true]);
            $category->tenant_id = $tenant->id;
            $category->save();

            return $category;
        });

        $this->assertSame($tenant->id, $category->tenant_id);
    }

    // ── الف-۵ ────────────────────────────────────────────────────────────────

    /** rollback با بیش از یک مستأجر باید جلوی خودش را بگیرد */
    public function test_backfill_rollback_is_blocked_with_multiple_tenants(): void
    {
        $this->makeTenant('A');
        $this->makeTenant('B');

        $migration = require database_path('migrations/2026_08_25_000003_backfill_default_tenant.php');

        $this->expectException(RuntimeException::class);

        $migration->down();
    }

    /** با یک مستأجر همچنان قابل rollback است */
    public function test_backfill_rollback_is_allowed_with_single_tenant(): void
    {
        $tenant = $this->makeTenant('A');

        $migration = require database_path('migrations/2026_08_25_000003_backfill_default_tenant.php');
        $migration->down();

        $this->assertNull(DB::table('users')->value('tenant_id'));
    }
}
