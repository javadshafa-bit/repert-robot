<?php

namespace Tests\Feature;

use App\Models\BotState;
use App\Models\Category;
use App\Models\Province;
use App\Models\Report;
use App\Models\Representative;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MultiTenancyTest extends TestCase
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
            'email'                => str_replace(' ', '', $name) . '@example.com',
            'status'               => $status,
            'bot_token'            => $token,
            'webhook_secret'       => Tenant::generateWebhookSecret(),
            // سازمان فعال باید اشتراک معتبر هم داشته باشد، وگرنه گیت اشتراک جلویش را می‌گیرد
            'subscription_ends_at' => $status === Tenant::STATUS_ACTIVE ? now()->addMonth() : null,
        ]);
    }

    private function makeAdmin(Tenant $tenant): User
    {
        return User::create([
            'tenant_id'      => $tenant->id,
            'name'           => 'admin ' . $tenant->id,
            'email'          => 'admin' . $tenant->id . '@example.com',
            'password'       => 'password123',
            'is_super_admin' => true,
        ]);
    }

    /** داده‌ای که در بستر یک مستأجر ساخته می‌شود */
    private function seedData(Tenant $tenant): array
    {
        return TenantContext::forTenant($tenant, function () {
            $province = Province::create(['name' => 'تهران']);
            $category = Category::create(['name' => 'دسته ' . TenantContext::id(), 'sort_order' => 1, 'is_active' => true]);
            $rep      = Representative::create([
                'province_id'  => $province->id,
                'first_name'   => 'نماینده',
                'last_name'    => 'FamilyOfTenant' . TenantContext::id(),
                'phone_number' => '09120000000',
                'chat_id'      => '555',
            ]);
            $report = Report::create([
                'representative_id' => $rep->id,
                'category_id'       => $category->id,
                'jalali_month'      => '1404-05',
                'data'              => [],
            ]);

            return compact('province', 'category', 'rep', 'report');
        });
    }

    public function test_registration_logs_in_and_sends_owner_to_billing(): void
    {
        $this->post('/register', [
            'organization'          => 'سازمان تست',
            'owner_name'            => 'مدیر تست',
            'owner_phone'           => '09120000000',
            'email'                 => 'new@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('billing.index'));

        $tenant = Tenant::where('email', 'new@example.com')->first();
        $this->assertNotNull($tenant);
        $this->assertSame(Tenant::STATUS_PENDING_PAYMENT, $tenant->status);

        // برخلاف فاز ۱، کاربر بلافاصله وارد شده است — وگرنه هرگز به صفحه‌ی پرداخت نمی‌رسد
        $this->assertAuthenticated();

        // ولی تا پرداخت، پنل بسته است
        $this->get('/admin/reports')->assertRedirect(route('billing.index'));

        // استان‌ها بدون نیاز به تایید کسی ساخته شده‌اند
        $this->assertSame(31, \DB::table('provinces')->where('tenant_id', $tenant->id)->count());
    }

    public function test_platform_admin_can_suspend_and_resume_a_tenant(): void
    {
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['username' => 'bot']], 200)]);

        $platformAdmin = User::create([
            'name'              => 'platform',
            'email'             => 'platform@example.com',
            'password'          => 'password123',
            'is_platform_admin' => true,
        ]);

        $tenant = $this->makeTenant('A');
        $admin  = $this->makeAdmin($tenant);

        $this->actingAs($platformAdmin)
            ->post(route('platform.tenants.suspend', $tenant), ['reason' => 'بدهی'])
            ->assertRedirect();

        $this->assertSame(Tenant::STATUS_SUSPENDED, $tenant->fresh()->status);
        $this->assertSame('TOKEN', $tenant->fresh()->bot_token, 'تعلیق نباید توکن را پاک کند.');

        // کاربر سازمانِ معلق نمی‌تواند وارد شود
        $this->post('/logout');
        $this->post('/login', ['email' => $admin->email, 'password' => 'password123'])
            ->assertSessionHasErrors('email');

        $this->actingAs($platformAdmin)
            ->post(route('platform.tenants.resume', $tenant))
            ->assertRedirect();

        $this->assertSame(Tenant::STATUS_ACTIVE, $tenant->fresh()->status);
    }

    public function test_admin_panel_only_shows_own_tenant_data(): void
    {
        $tenantA = $this->makeTenant('A');
        $tenantB = $this->makeTenant('B');
        $dataA   = $this->seedData($tenantA);
        $dataB   = $this->seedData($tenantB);

        $adminA = $this->makeAdmin($tenantA);

        $this->actingAs($adminA)
            ->get(route('admin.representatives.index'))
            ->assertOk()
            ->assertSee($dataA['rep']->last_name)
            ->assertDontSee($dataB['rep']->last_name);
    }

    public function test_dashboard_and_settings_pages_render_for_tenant_admin(): void
    {
        $tenant = $this->makeTenant('A');
        $this->seedData($tenant);
        $admin = $this->makeAdmin($tenant);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee($tenant->fresh()->webhook_secret);
    }

    public function test_cross_tenant_record_returns_404(): void
    {
        $tenantA = $this->makeTenant('A');
        $tenantB = $this->makeTenant('B');
        $this->seedData($tenantA);
        $dataB = $this->seedData($tenantB);

        $adminA = $this->makeAdmin($tenantA);

        $this->actingAs($adminA)
            ->get(route('admin.reports.show', $dataB['report']->id))
            ->assertNotFound();

        $this->actingAs($adminA)
            ->get(route('admin.representatives.edit', $dataB['rep']->id))
            ->assertNotFound();
    }

    public function test_same_phone_and_chat_id_allowed_in_two_tenants(): void
    {
        $tenantA = $this->makeTenant('A');
        $tenantB = $this->makeTenant('B');

        $this->seedData($tenantA);
        $this->seedData($tenantB);

        $this->assertSame(2, \DB::table('representatives')->where('phone_number', '09120000000')->count());
        $this->assertSame(2, \DB::table('representatives')->where('chat_id', '555')->count());
    }

    public function test_settings_are_isolated_per_tenant(): void
    {
        $tenantA = $this->makeTenant('A');
        $tenantB = $this->makeTenant('B');

        TenantContext::forTenant($tenantA, fn() => Setting::set('welcome_message', 'سلام A'));
        TenantContext::forTenant($tenantB, fn() => Setting::set('welcome_message', 'سلام B'));

        $this->assertSame('سلام A', TenantContext::forTenant($tenantA, fn() => Setting::get('welcome_message')));
        $this->assertSame('سلام B', TenantContext::forTenant($tenantB, fn() => Setting::get('welcome_message')));
    }

    public function test_webhook_is_routed_to_the_right_tenant(): void
    {
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);

        $tenantA = $this->makeTenant('A');
        $tenantB = $this->makeTenant('B');

        $update = [
            'message' => [
                'chat' => ['id' => '999'],
                'text' => '/start',
            ],
        ];

        $this->postJson('/api/bot/webhook/' . $tenantA->webhook_secret, $update)->assertOk();

        $this->assertSame(1, BotState::withoutGlobalScopes()->where('tenant_id', $tenantA->id)->count());
        $this->assertSame(0, BotState::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->count());
    }

    public function test_suspended_tenant_webhook_is_ignored(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $tenant = $this->makeTenant('A', Tenant::STATUS_SUSPENDED);

        $this->postJson('/api/bot/webhook/' . $tenant->webhook_secret, [
            'message' => ['chat' => ['id' => '999'], 'text' => '/start'],
        ])->assertOk();

        $this->assertSame(0, BotState::withoutGlobalScopes()->count());
    }

    public function test_unknown_webhook_secret_returns_404(): void
    {
        $this->postJson('/api/bot/webhook/not-a-real-secret', [])->assertNotFound();
    }

    public function test_due_broadcasts_only_reach_own_tenant_representatives(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $tenantA = $this->makeTenant('A', Tenant::STATUS_ACTIVE, 'TOKEN-A');
        $tenantB = $this->makeTenant('B', Tenant::STATUS_ACTIVE, 'TOKEN-B');

        foreach ([['t' => $tenantA, 'chat' => '111'], ['t' => $tenantB, 'chat' => '222']] as $row) {
            TenantContext::forTenant($row['t'], function () use ($row) {
                $province = Province::create(['name' => 'تهران']);
                Representative::create([
                    'province_id'  => $province->id,
                    'first_name'   => 'نماینده',
                    'last_name'    => 'x',
                    'phone_number' => '0912' . $row['chat'],
                    'chat_id'      => $row['chat'],
                    'is_connected' => true,
                ]);

                \App\Models\BroadcastMessage::create([
                    'title'         => 'پیام ' . TenantContext::id(),
                    'body'          => 'متن ' . TenantContext::id(),
                    'schedule_type' => 'once',
                    'scheduled_at'  => now()->subMinute(),
                    'status'        => 'pending',
                ]);
            });
        }

        $this->artisan('broadcast:send-due')->assertSuccessful();

        // پیام هر سازمان فقط با توکن خودش و به chat_id نماینده‌ی خودش رفته است
        Http::assertSent(fn($request) => str_contains($request->url(), 'TOKEN-A')
            && ($request['chat_id'] ?? null) === '111');

        Http::assertSent(fn($request) => str_contains($request->url(), 'TOKEN-B')
            && ($request['chat_id'] ?? null) === '222');

        Http::assertNotSent(fn($request) => str_contains($request->url(), 'TOKEN-A')
            && ($request['chat_id'] ?? null) === '222');
    }

    public function test_suspended_tenant_user_cannot_login(): void
    {
        $tenant = $this->makeTenant('A', Tenant::STATUS_SUSPENDED);
        $admin  = $this->makeAdmin($tenant);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
