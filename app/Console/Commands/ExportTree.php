<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportTree extends Command
{
    protected $signature   = 'tree:export {--output=tree_export.json} {--tenant= : شناسه سازمان (پیش‌فرض: اولین سازمان)}';
    protected $description = 'Export category tree (categories + fields + options) of one tenant to JSON';

    public function handle()
    {
        $tenantId = $this->option('tenant') ?: Tenant::min('id');

        if (!$tenantId) {
            $this->error('هیچ سازمانی در دیتابیس وجود ندارد.');
            return 1;
        }

        $data = [
            'tenant_id'       => (int) $tenantId,
            'categories'      => DB::table('categories')->where('tenant_id', $tenantId)->get(),
            'category_fields' => DB::table('category_fields')->where('tenant_id', $tenantId)->get(),
            'field_options'   => DB::table('field_options')->where('tenant_id', $tenantId)->get(),
            'departments'     => DB::table('departments')->where('tenant_id', $tenantId)->get(),
        ];

        $output = $this->option('output');
        file_put_contents($output, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->info("Exported tenant #{$tenantId} to: $output");

        return 0;
    }
}
