<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportTree extends Command
{
    protected $signature   = 'tree:export {--output=tree_export.json}';
    protected $description = 'Export category tree (categories + fields + options) to JSON';

    public function handle()
    {
        $data = [
            'categories'      => DB::table('categories')->get(),
            'category_fields' => DB::table('category_fields')->get(),
            'field_options'   => DB::table('field_options')->get(),
            'departments'     => DB::table('departments')->get(),
        ];

        $output = $this->option('output');
        file_put_contents($output, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->info("Exported to: $output");
    }
}
