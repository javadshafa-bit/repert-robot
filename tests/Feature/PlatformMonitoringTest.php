<?php

namespace Tests\Feature;

use App\Models\BroadcastMessage;
use App\Models\Category;
use App\Models\Department;
use App\Models\Payment;
use App\Models\Province;
use App\Models\Report;
use App\Models\Representative;
use App\Models\SubscriptionLog;
use App\Models\Tenant;
use App\Models\User;
use App\Support\JalaliDate;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * صفحات نظارتی سوپرادمین: ایزوله بودن داده‌ی هر سازمان، فقط‌خواندنی بودن،
 * و مدیریت دستی اشتراک.
 */
class PlatformMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private User $platformAdmin;
    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::forget();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['username' => 'bot']], 200)]);

        $this->platformAdmin = User::create([
            'name'              => 'platform',
            'email'             => 'platform@example.com',
            'password'          => 'password123',
            'is_platform_admin' => true,
        ]);

        $this->tenantA = $this->makeTenantWithData('A');
        $this->tenantB = $this->makeTenantWithData('B');
    }

    protected function tearDown(): void
    {
        TenantContext::forget();
        parent::tearDown();
    }

    private function makeTenantWithData(string $tag): Tenant
    {
        $tenant = Tenant::create([
            'name'                 => "سازمان{$tag}",
            'owner_name'           => "مدیر{$tag}",
            'email'                => strtolower($tag) . '@example.com',
            'status'               => Tenant::STATUS_ACTIVE,
            'bot_token'            => 'TOKEN-' . $tag,
            'webhook_secret'       => Tenant::generateWebhookSecret(),
            'subscription_ends_at' => now()->addDays(30),
        ]);

        TenantContext::forTenant($tenant, function () use ($tag) {
            $province = Province::create(['name' => 'تهران']);
            $category = Category::create(['name' => "دسته{$tag}", 'sort_order' => 1, 'is_active' => true]);
            $dept     = Department::create(['name' => "دپارتمان{$tag}", 'sort_order' => 1, 'is_active' => true]);

            $rep = Representative::create([
                'province_id'  => $province->id,
                'first_name'   => 'نماینده',
                'last_name'    => "خانوادگی{$tag}",
                'phone_number' => '0912000000' . ($tag === 'A' ? '1' : '2'),
                'chat_id'      => $tag === 'A' ? '111' : '222',
            ]);

            Report::create([
                'representative_id' => $rep->id,
                'category_id'       => $category->id,
                'department_id'     => $dept->id,
                'jalali_month'      => '1405-05',
                'data'              => [['label' => "پرسش{$tag}", 'type' => 'text', 'value' => "پاسخ{$tag}"]],
            ]);

            BroadcastMessage::create([
                'title'         => "پیام{$tag}",
                'body'          => 'متن',
                'schedule_type' => 'once',
                'scheduled_at'  => now()->addDay(),
                'status'        => 'pending',
            ]);

            Payment::create([
                'amount'          => $tag === 'A' ? 111111 : 222222,
                'original_amount' => $tag === 'A' ? 111111 : 222222,
                'days_granted'    => 10,
                'status'          => Payment::STATUS_PAID,
                'authority'       => 'AUTH-' . $tag,
                'ref_id'          => 'REF-' . $tag,
                'paid_at'         => now(),
            ]);
        });

        TenantContext::forget();

        return $tenant;
    }

    public static function monitorRoutes(): array
    {
        return [
            'representatives'   => ['platform.tenants.representatives', 'خانوادگیA', 'خانوادگیB'],
            'departments'       => ['platform.tenants.departments',     'دپارتمانA', 'دپارتمانB'],
            'categories'        => ['platform.tenants.categories',      'دستهA',     'دستهB'],
            'reports'           => ['platform.tenants.reports',         'خانوادگیA', 'خانوادگیB'],
            'broadcasts'        => ['platform.tenants.broadcasts',      'پیامA',     'پیامB'],
            'payments'          => ['platform.tenants.payments',        'REF-A',     'REF-B'],
        ];
    }

    /** صفحه‌ی نظارتی سازمان A فقط رکوردهای A را نشان می‌دهد */
    #[DataProvider('monitorRoutes')]
    public function test_monitor_pages_only_show_the_selected_tenant(string $route, string $seen, string $notSeen): void
    {
        $this->actingAs($this->platformAdmin)
            ->get(route($route, $this->tenantA))
            ->assertOk()
            ->assertSee($seen)
            ->assertDontSee($notSeen);
    }

    public function test_report_detail_is_scoped_to_its_tenant(): void
    {
        $reportA = TenantContext::forTenant($this->tenantA, fn () => Report::firstOrFail());
        $reportB = TenantContext::forTenant($this->tenantB, fn () => Report::firstOrFail());
        TenantContext::forget();

        $this->actingAs($this->platformAdmin)
            ->get(route('platform.tenants.report', [$this->tenantA, $reportA->id]))
            ->assertOk()
            ->assertSee('پاسخA')
            ->assertDontSee('پاسخB');

        // گزارش سازمان B از مسیر سازمان A قابل باز کردن نیست
        $this->actingAs($this->platformAdmin)
            ->get(route('platform.tenants.report', [$this->tenantA, $reportB->id]))
            ->assertNotFound();
    }

    /** بعد از یک درخواست platform نباید مستأجری روی TenantContext باقی مانده باشد */
    public function test_platform_request_leaves_no_tenant_context_behind(): void
    {
        $this->actingAs($this->platformAdmin)->get(route('platform.tenants.reports', $this->tenantA))->assertOk();

        $this->assertFalse(TenantContext::check(), 'TenantContext بعد از درخواست platform پاک نشده است.');
    }

    /** صفحات نظارتی باید فقط GET باشند */
    public function test_monitor_routes_are_read_only(): void
    {
        $writable = collect(app('router')->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->getName() ?? '', 'platform.tenants.'))
            ->filter(fn ($route) => array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']))
            ->map(fn ($route) => $route->getName())
            ->values()
            ->all();

        // تنها استثناها: تعلیق، رفع تعلیق، و تعیین دوره‌ی اشتراک
        sort($writable);
        $this->assertSame([
            'platform.tenants.resume',
            'platform.tenants.subscription',
            'platform.tenants.suspend',
        ], $writable, 'یک مسیر نوشتنیِ تازه به ناحیه‌ی نظارتی اضافه شده است.');
    }

    // ── مدیریت دستی اشتراک ──────────────────────────────────────────────────

    public function test_quick_extend_adds_days_and_writes_a_log(): void
    {
        $before = $this->tenantA->subscription_ends_at;

        $this->actingAs($this->platformAdmin)
            ->post(route('platform.tenants.subscription', $this->tenantA), ['mode' => 'extend', 'days' => 30])
            ->assertRedirect();

        $this->tenantA->refresh();
        $this->assertSame(60, $this->tenantA->subscriptionDaysLeft());

        $log = TenantContext::forTenant($this->tenantA, fn () => SubscriptionLog::latest('id')->firstOrFail());
        TenantContext::forget();

        $this->assertSame(SubscriptionLog::SOURCE_MANUAL, $log->source);
        $this->assertSame($this->platformAdmin->id, $log->user_id);
        $this->assertEquals($before->timestamp, $log->from_ends_at->timestamp);
        $this->assertStringContainsString('30', $log->note);
    }

    public function test_setting_an_exact_jalali_date_works(): void
    {
        $target = JalaliDate::parse('1405/12/29', endOfDay: true);

        $this->actingAs($this->platformAdmin)
            ->post(route('platform.tenants.subscription', $this->tenantA), [
                'mode' => 'set', 'ends_at' => '1405/12/29', 'note' => 'توافق ویژه',
            ])
            ->assertRedirect();

        $this->assertSame(
            $target->toDateString(),
            $this->tenantA->fresh()->subscription_ends_at->toDateString()
        );
    }

    public function test_making_subscription_unlimited(): void
    {
        $this->actingAs($this->platformAdmin)
            ->post(route('platform.tenants.subscription', $this->tenantA), ['mode' => 'set', 'is_unlimited' => 1])
            ->assertRedirect();

        $tenant = $this->tenantA->fresh();

        $this->assertTrue($tenant->is_unlimited);
        $this->assertTrue($tenant->hasActiveSubscription());
        $this->assertNull($tenant->subscriptionDaysLeft());
    }

    /** کوتاه کردن دوره: سازمان بلافاصله منقضی و وبهوک رباتش حذف می‌شود */
    public function test_shortening_the_period_expires_the_tenant_and_removes_webhook(): void
    {
        $this->actingAs($this->platformAdmin)
            ->post(route('platform.tenants.subscription', $this->tenantA), [
                'mode' => 'set', 'ends_at' => JalaliDate::format(now()->subDay()),
            ])
            ->assertRedirect();

        $tenant = $this->tenantA->fresh();

        $this->assertSame(Tenant::STATUS_EXPIRED, $tenant->status);
        $this->assertFalse($tenant->botIsUsable());
        $this->assertSame('TOKEN-A', $tenant->bot_token, 'توکن نباید پاک شود.');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'deleteWebhook'));
    }

    public function test_invalid_date_is_rejected(): void
    {
        $this->actingAs($this->platformAdmin)
            ->post(route('platform.tenants.subscription', $this->tenantA), ['mode' => 'set', 'ends_at' => 'فردا'])
            ->assertSessionHasErrors('ends_at');
    }

    public function test_set_mode_requires_either_a_date_or_unlimited(): void
    {
        $this->actingAs($this->platformAdmin)
            ->post(route('platform.tenants.subscription', $this->tenantA), ['mode' => 'set'])
            ->assertSessionHasErrors('ends_at');
    }
}
