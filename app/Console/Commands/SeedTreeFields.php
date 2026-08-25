<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\TenantContext;
use Database\Seeders\TreeFieldsSeeder;
use Illuminate\Console\Command;

/**
 * اجرای TreeFieldsSeeder برای یک سازمان مشخص.
 *
 *   php artisan tenants:seed-tree 1
 *
 * ابزار توسعه است، نه بخشی از deploy. سازمان اجباری است چون seeder با DB::table
 * می‌نویسد و بدون tenant_id رکوردهای نامرئی می‌سازد.
 */
class SeedTreeFields extends Command
{
    protected $signature   = 'tenants:seed-tree {tenant : شناسه سازمان}';
    protected $description = 'ساخت ساختار درختی نمونه (هنری/تجسمی/اجرایی) برای یک سازمان';

    public function handle(): int
    {
        $tenant = Tenant::find($this->argument('tenant'));

        if (!$tenant) {
            $this->error('سازمان با این شناسه پیدا نشد.');

            return self::FAILURE;
        }

        $this->info("سازمان مقصد: #{$tenant->id} — {$tenant->name}");

        TenantContext::forTenant($tenant, function () {
            $seeder = new TreeFieldsSeeder();
            $seeder->setCommand($this);
            $seeder->run();
        });

        return self::SUCCESS;
    }
}
