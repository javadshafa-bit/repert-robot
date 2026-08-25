<?php

namespace Tests\Feature;

use App\Models\DiscountCode;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * هر صفحه‌ای که تست اختصاصی ندارد، حداقل باید بدون خطا رندر شود.
 * (خطاهای Blade فقط موقع رندر واقعی معلوم می‌شوند.)
 */
class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::forget();
        parent::tearDown();
    }

    public function test_every_platform_get_page_renders(): void
    {
        $admin = User::create([
            'name'              => 'platform',
            'email'             => 'platform@example.com',
            'password'          => 'password123',
            'is_platform_admin' => true,
        ]);

        $tenant = Tenant::create([
            'name'                 => 'سازمان',
            'owner_name'           => 'مدیر',
            'email'                => 'org@example.com',
            'status'               => Tenant::STATUS_EXPIRED,
            'webhook_secret'       => Tenant::generateWebhookSecret(),
            'subscription_ends_at' => now()->subMonths(8),   // کاندیدای حذف در داشبورد
        ]);

        $code = DiscountCode::create(['code' => 'SMOKE', 'percent' => 15, 'is_active' => true]);

        $urls = [
            route('platform.dashboard'),
            route('platform.tenants.index'),
            route('platform.tenants.show', $tenant),
            route('platform.tenants.representatives', $tenant),
            route('platform.tenants.departments', $tenant),
            route('platform.tenants.categories', $tenant),
            route('platform.tenants.reports', $tenant),
            route('platform.tenants.broadcasts', $tenant),
            route('platform.tenants.payments', $tenant),
            route('platform.tenants.subscription-logs', $tenant),
            route('platform.discount-codes.index'),
            route('platform.discount-codes.create'),
            route('platform.discount-codes.edit', $code),
            route('platform.settings.edit'),
        ];

        foreach ($urls as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_billing_pages_render_for_a_tenant_admin(): void
    {
        $tenant = Tenant::create([
            'name'           => 'سازمان',
            'owner_name'     => 'مدیر',
            'email'          => 'org@example.com',
            'status'         => Tenant::STATUS_PENDING_PAYMENT,
            'webhook_secret' => Tenant::generateWebhookSecret(),
        ]);

        $admin = User::create([
            'tenant_id'      => $tenant->id,
            'name'           => 'admin',
            'email'          => 'admin@example.com',
            'password'       => 'password123',
            'is_super_admin' => true,
        ]);

        $this->actingAs($admin)->get(route('billing.index'))->assertOk();
        $this->actingAs($admin)->get(route('billing.invoices'))->assertOk();
    }
}
