<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportTree extends Command
{
    protected $signature   = 'tree:import {file : Path to JSON export file}';
    protected $description = 'Import category tree from JSON (clears existing tree data first)';

    public function handle()
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("File not found: $file");
            return 1;
        }

        $data = json_decode(file_get_contents($file), true);

        if (!$this->confirm('This will CLEAR existing categories, fields, and options. Continue?')) {
            return 0;
        }

        DB::statement('PRAGMA foreign_keys = OFF');

        DB::table('field_options')->truncate();
        DB::table('category_fields')->truncate();
        DB::table('categories')->truncate();
        DB::table('departments')->truncate();

        foreach (collect($data['departments'] ?? [])->chunk(100) as $chunk) {
            DB::table('departments')->insert($chunk->map(fn($r) => (array) $r)->toArray());
        }
        $this->info('✓ departments imported: ' . count($data['departments'] ?? []));

        foreach (collect($data['categories'])->chunk(100) as $chunk) {
            DB::table('categories')->insert($chunk->map(fn($r) => (array) $r)->toArray());
        }
        $this->info('✓ categories imported: ' . count($data['categories']));

        foreach (collect($data['category_fields'])->chunk(100) as $chunk) {
            DB::table('category_fields')->insert($chunk->map(fn($r) => (array) $r)->toArray());
        }
        $this->info('✓ category_fields imported: ' . count($data['category_fields']));

        foreach (collect($data['field_options'])->chunk(100) as $chunk) {
            DB::table('field_options')->insert($chunk->map(fn($r) => (array) $r)->toArray());
        }
        $this->info('✓ field_options imported: ' . count($data['field_options']));

        DB::statement('PRAGMA foreign_keys = ON');

        $this->info('Done!');
    }
}
