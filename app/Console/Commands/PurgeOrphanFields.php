<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\CategoryField;
use App\Models\FieldOption;
use App\Models\Report;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * گزارش و پاک‌سازی فیلدهای «غیرقابل‌دسترس» فرم‌ساز.
 *
 * تعریف: فیلدی که با پیمایش از فیلدهای سطح اول (هر دو والدش null) به آن
 * نمی‌رسیم. سه دسته را پوشش می‌دهد:
 *   - والدِ آویزان: parent_option_id یا parent_field_id به رکوردی اشاره می‌کند که نیست
 *   - حلقه: زنجیره‌ی والد به خودش برمی‌گردد، پس هرگز به ریشه نمی‌رسد
 *   - نوادهٔ یتیم: والدش هست ولی خودِ والد غیرقابل‌دسترس است
 *
 * این رکوردها هیچ‌جا رندر نمی‌شوند (لیست ریشه با whereNull(parent_*) فیلتر
 * می‌شود) و ربات هم هرگز سراغشان نمی‌رود، ولی در جدول می‌مانند.
 *
 * منشأ: تا پیش از افزوده شدن حذف بازگشتی، delete یک فیلد زیرمجموعه‌اش را
 * یتیم می‌گذاشت. حذف حالا بازگشتی است، پس این دستور فقط برای پاک کردن
 * ته‌مانده‌های قدیمی است.
 *
 *   php artisan fields:purge-orphans            گزارش (چیزی حذف نمی‌شود)
 *   php artisan fields:purge-orphans --force    حذف واقعی
 */
class PurgeOrphanFields extends Command
{
    protected $signature = 'fields:purge-orphans
                            {--force : واقعاً حذف کن (پیش‌فرض فقط گزارش است)}
                            {--category= : فقط یک دسته‌بندی}';

    protected $description = 'گزارش و پاک‌سازی فیلدهای غیرقابل‌دسترس فرم‌ساز';

    public function handle(): int
    {
        return TenantContext::withoutScope(fn () => $this->purge());
    }

    /** نام run() رزرو شده است (public در Illuminate\Console\Command) — از آن استفاده نکن */
    private function purge(): int
    {
        $categoryFilter = $this->option('category');

        $fields = CategoryField::query()
            ->when($categoryFilter, fn ($q) => $q->where('category_id', $categoryFilter))
            ->get(['id', 'category_id', 'tenant_id', 'label', 'type', 'parent_option_id', 'parent_field_id']);

        if ($fields->isEmpty()) {
            $this->info('هیچ فیلدی پیدا نشد.');
            return self::SUCCESS;
        }

        $options = FieldOption::query()
            ->whereIn('field_id', $fields->pluck('id'))
            ->get(['id', 'field_id']);

        $byId            = $fields->keyBy('id');
        $optionsByField  = $options->groupBy('field_id');
        $optionExists    = $options->keyBy('id');
        $childrenByOpt   = $fields->whereNotNull('parent_option_id')->groupBy('parent_option_id');
        $childrenByField = $fields->whereNotNull('parent_field_id')->groupBy('parent_field_id');

        // ── پیمایش رو به پایین از فیلدهای سطح اول ──────────────────────────
        $stack = $fields
            ->filter(fn ($f) => is_null($f->parent_option_id) && is_null($f->parent_field_id))
            ->pluck('id')
            ->all();

        $reachable = [];
        while ($stack) {
            $id = array_pop($stack);
            if (isset($reachable[$id])) continue;      // محافظ حلقه
            $reachable[$id] = true;

            foreach ($optionsByField[$id] ?? [] as $opt) {
                foreach ($childrenByOpt[$opt->id] ?? [] as $cf) {
                    $stack[] = $cf->id;
                }
            }
            foreach ($childrenByField[$id] ?? [] as $cf) {
                $stack[] = $cf->id;
            }
        }

        $orphans = $fields->reject(fn ($f) => isset($reachable[$f->id]))->values();

        $this->newLine();
        $this->line('کل فیلدها:        ' . $fields->count());
        $this->line('قابل دسترس:       ' . count($reachable));
        $this->line('غیرقابل دسترس:    ' . $orphans->count());

        if ($orphans->isEmpty()) {
            $this->newLine();
            $this->info('✅ هیچ رکورد یتیمی وجود ندارد.');
            return self::SUCCESS;
        }

        // ── دسته‌بندی علت ──────────────────────────────────────────────────
        $reasons = ['والد آویزان (گزینه)' => 0, 'والد آویزان (فیلد)' => 0, 'نواده یا حلقه' => 0];
        foreach ($orphans as $f) {
            if ($f->parent_option_id && !$optionExists->has($f->parent_option_id)) {
                $reasons['والد آویزان (گزینه)']++;
            } elseif ($f->parent_field_id && !$byId->has($f->parent_field_id)) {
                $reasons['والد آویزان (فیلد)']++;
            } else {
                $reasons['نواده یا حلقه']++;
            }
        }

        $this->newLine();
        $this->line('علت:');
        foreach ($reasons as $k => $v) {
            if ($v) $this->line(sprintf('   %-24s %d', $k, $v));
        }

        // ── کدام یتیم‌ها زمانی در گزارشی استفاده شده‌اند؟ ───────────────────
        // محتوای گزارش در همان رکورد گزارش snapshot می‌شود (label/type/value)،
        // پس حذف فیلد نمایش گزارش‌های قدیمی را خراب نمی‌کند. تنها پیامدش این
        // است که دیگر نمی‌شود آرشیو را روی آن فیلد فیلتر کرد.
        $usedFieldIds = $this->fieldIdsUsedInReports();
        $orphanIds    = $orphans->pluck('id')->all();
        $usedOrphans  = array_values(array_intersect($orphanIds, $usedFieldIds));

        $catNames = Category::whereIn('id', $fields->pluck('category_id')->unique())
            ->pluck('name', 'id');

        // ── تفکیک به‌ازای دسته‌بندی ─────────────────────────────────────────
        $this->newLine();
        $this->line('تفکیک به‌ازای دسته‌بندی:');

        $rows = $orphans->groupBy('category_id')
            ->map(function ($group, $catId) use ($fields, $catNames, $usedFieldIds) {
                $total    = $fields->where('category_id', $catId)->count();
                $usedHere = count(array_intersect($group->pluck('id')->all(), $usedFieldIds));
                return [
                    'name'    => $catNames[$catId] ?? ('#' . $catId),
                    'total'   => $total,
                    'orphans' => $group->count(),
                    'pct'     => $total ? round($group->count() * 100 / $total) . '٪' : '—',
                    'used'    => $usedHere ?: '—',
                ];
            })
            ->sortByDesc('orphans')
            ->values();

        $this->table(
            ['دسته‌بندی', 'کل فیلدها', 'یتیم', 'نسبت', 'در گزارش استفاده شده'],
            $rows->map(fn ($r) => [$r['name'], $r['total'], $r['orphans'], $r['pct'], $r['used']])->all()
        );

        if ($usedOrphans) {
            $this->warn(count($usedOrphans) . ' فیلد یتیم زمانی در گزارشی استفاده شده‌اند.');
            $this->line('   محتوای آن گزارش‌ها سالم می‌ماند (در خود رکورد گزارش ذخیره شده).');
            $this->line('   فقط دیگر نمی‌توانی آرشیو را روی این فیلدها فیلتر کنی.');
            $this->line('   شناسه‌ها: ' . implode(', ', array_slice($usedOrphans, 0, 30))
                . (count($usedOrphans) > 30 ? ' …' : ''));
        } else {
            $this->info('هیچ‌کدام از این یتیم‌ها در هیچ گزارشی استفاده نشده‌اند.');
        }

        // ── نمونه‌ها ───────────────────────────────────────────────────────
        $this->newLine();
        $this->line('نمونه (حداکثر ۲۰ مورد):');
        $this->table(
            ['id', 'دسته‌بندی', 'نوع', 'عنوان'],
            $orphans->take(20)->map(fn ($f) => [
                $f->id,
                $catNames[$f->category_id] ?? ('#' . $f->category_id),
                $f->type,
                mb_substr((string) $f->label, 0, 40),
            ])->all()
        );

        $orphanOptionCount = FieldOption::whereIn('field_id', $orphans->pluck('id'))->count();
        if ($orphanOptionCount) {
            $this->line("همراهشان {$orphanOptionCount} گزینه هم حذف می‌شود (cascade).");
        }

        if (!$this->option('force')) {
            $this->newLine();
            $this->warn('این فقط گزارش بود — چیزی حذف نشد.');
            $this->line('برای حذف واقعی، اول بکاپ بگیر و بعد:');
            $this->line('   php artisan fields:purge-orphans --force');
            return self::SUCCESS;
        }

        // ── حذف ───────────────────────────────────────────────────────────
        if (!$this->confirm("آیا {$orphans->count()} فیلد غیرقابل‌دسترس حذف شود؟ (بکاپ گرفته‌ای؟)", false)) {
            $this->info('لغو شد.');
            return self::SUCCESS;
        }

        $deleted = 0;
        DB::transaction(function () use ($orphans, &$deleted) {
            // همه‌شان می‌روند، پس بازگشت لازم نیست — گزینه‌هایشان cascade می‌شوند
            foreach ($orphans->pluck('id')->chunk(500) as $chunk) {
                $deleted += CategoryField::whereIn('id', $chunk)->delete();
            }
        });

        $this->newLine();
        $this->info("✅ {$deleted} فیلد حذف شد.");
        $this->line('دوباره بدون --force اجرا کن تا مطمئن شوی چیزی نمانده.');

        return self::SUCCESS;
    }

    /**
     * شناسه‌ی هر فیلدی که در دست‌کم یک گزارش ثبت‌شده آمده است.
     *
     * دو فرمت در جدول reports وجود دارد:
     *   جدید: [{field_id, label, type, value}, ...]
     *   قدیم: {field_id: value, ...}
     *
     * @return int[]
     */
    private function fieldIdsUsedInReports(): array
    {
        $used = [];

        Report::query()->select(['id', 'data'])->cursor()->each(function ($r) use (&$used) {
            $data = is_array($r->data) ? $r->data : [];
            if (!$data) return;

            if (!isset($data[0])) {                       // فرمت قدیم: کلید = شناسه فیلد
                foreach (array_keys($data) as $k) {
                    if (is_numeric($k)) $used[(int) $k] = true;
                }
                return;
            }

            foreach ($data as $item) {
                if (is_array($item) && isset($item['field_id'])) {
                    $used[(int) $item['field_id']] = true;
                }
            }
        });

        return array_keys($used);
    }
}
