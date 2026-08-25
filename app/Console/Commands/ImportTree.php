<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportTree extends Command
{
    protected $signature   = 'tree:import {file : Path to JSON export file}
                              {--tenant= : شناسه سازمان مقصد (پیش‌فرض: tenant_id داخل فایل)}';
    protected $description = 'Import category tree from JSON into one tenant (clears that tenant tree data first)';

    public function handle()
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("File not found: $file");
            return 1;
        }

        $data = json_decode(file_get_contents($file), true);

        $tenantId = (int) ($this->option('tenant') ?: ($data['tenant_id'] ?? 0));

        if (!$tenantId || !Tenant::whereKey($tenantId)->exists()) {
            $this->error('سازمان مقصد مشخص نیست. با --tenant=ID اجرا کنید.');
            return 1;
        }

        if (!$this->confirm("This will CLEAR categories, fields, and options of tenant #{$tenantId}. Continue?")) {
            return 0;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        }

        // فقط داده‌ی همین سازمان پاک می‌شود، نه کل جدول
        DB::table('field_options')->where('tenant_id', $tenantId)->delete();
        DB::table('category_fields')->where('tenant_id', $tenantId)->delete();
        DB::table('categories')->where('tenant_id', $tenantId)->delete();
        DB::table('departments')->where('tenant_id', $tenantId)->delete();

        foreach (['departments', 'categories', 'category_fields', 'field_options'] as $table) {
            $rows = collect($data[$table] ?? []);

            foreach ($rows->chunk(100) as $chunk) {
                DB::table($table)->insert(
                    $chunk->map(function ($row) use ($tenantId) {
                        $row = (array) $row;
                        $row['tenant_id'] = $tenantId;
                        return $row;
                    })->toArray()
                );
            }

            $this->info("✓ {$table} imported: " . $rows->count());
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        }

        $this->info('Done!');

        return 0;
    }
}
