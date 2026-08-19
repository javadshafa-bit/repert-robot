<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * انتقال داده‌ها از دیتابیس SQLite قدیمی (چابکان) به MySQL جدید.
 *
 *   php artisan db:import-sqlite /path/to/database.sqlite
 *
 * جدول‌های زیرساختی (migrations، cache، sessions، jobs) منتقل نمی‌شوند —
 * schema از قبل با migrate ساخته شده و این جدول‌ها داده‌ی معناداری ندارند.
 */
class ImportFromSqlite extends Command
{
    protected $signature = 'db:import-sqlite
                            {path : مسیر فایل database.sqlite}
                            {--dry-run : فقط گزارش بده، چیزی ننویس}';

    protected $description = 'انتقال داده‌ها از SQLite به دیتابیس فعلی (MySQL)';

    /** جدول‌هایی که نباید منتقل شوند */
    private const SKIP = [
        'migrations',
        'cache',
        'cache_locks',
        'sessions',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
        'sqlite_sequence',
    ];

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("فایل پیدا نشد: {$path}");
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        Config::set('database.connections.legacy', [
            'driver'   => 'sqlite',
            'database' => $path,
            'prefix'   => '',
            'foreign_key_constraints' => false,
        ]);

        $src = DB::connection('legacy');
        $dst = DB::connection();

        if ($dst->getDriverName() !== 'mysql') {
            $this->error('اتصال مقصد MySQL نیست. DB_CONNECTION را بررسی کنید.');
            return self::FAILURE;
        }

        $tables = collect($src->select(
            "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name"
        ))
            ->pluck('name')
            ->reject(fn ($t) => in_array($t, self::SKIP, true) || str_starts_with($t, 'sqlite_'))
            ->values();

        $this->info('جدول‌های یافت‌شده در SQLite: '.$tables->count());
        $this->newLine();

        $dst->statement('SET FOREIGN_KEY_CHECKS=0');

        $summary = [];

        foreach ($tables as $table) {
            if (! $dst->getSchemaBuilder()->hasTable($table)) {
                $summary[] = [$table, '—', '—', 'در MySQL وجود ندارد، رد شد'];
                continue;
            }

            $srcCount = $src->table($table)->count();

            // فقط ستون‌هایی که در هر دو طرف هستند منتقل می‌شوند
            $dstColumns = $dst->getSchemaBuilder()->getColumnListing($table);
            $srcColumns = $src->getSchemaBuilder()->getColumnListing($table);
            $shared     = array_values(array_intersect($srcColumns, $dstColumns));
            $dropped    = array_diff($srcColumns, $dstColumns);

            if ($dryRun) {
                $summary[] = [$table, $srcCount, 'dry-run', $dropped ? 'ستون بی‌استفاده: '.implode(',', $dropped) : ''];
                continue;
            }

            $dst->table($table)->truncate();

            $inserted = 0;
            $src->table($table)->orderBy(
                in_array('id', $srcColumns, true) ? 'id' : $srcColumns[0]
            )->chunk(200, function ($rows) use ($dst, $table, $shared, &$inserted) {
                $batch = $rows->map(function ($row) use ($shared) {
                    $out = [];
                    foreach ($shared as $col) {
                        $out[$col] = ((array) $row)[$col] ?? null;
                    }
                    return $out;
                })->all();

                $dst->table($table)->insert($batch);
                $inserted += count($batch);
            });

            $dstCount = $dst->table($table)->count();
            $status   = $srcCount === $dstCount ? '✅' : '⚠️ اختلاف!';
            if ($dropped) {
                $status .= ' (ستون بی‌استفاده: '.implode(',', $dropped).')';
            }

            $summary[] = [$table, $srcCount, $dstCount, $status];
            $this->line("  {$table}: {$inserted}");
        }

        $dst->statement('SET FOREIGN_KEY_CHECKS=1');

        $this->newLine();
        $this->table(['جدول', 'SQLite', 'MySQL', 'وضعیت'], $summary);

        if ($dryRun) {
            $this->warn('حالت dry-run — هیچ داده‌ای نوشته نشد.');
        } else {
            $this->info('انتقال کامل شد.');
        }

        return self::SUCCESS;
    }
}
