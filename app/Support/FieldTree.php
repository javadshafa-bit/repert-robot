<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * پیمایش درخت فرم‌ساز.
 *
 * تعریف «قابل دسترس»: از فیلدهای سطح اول (هر دو والدشان null) رو به پایین
 * می‌رویم — فیلد → گزینه‌هایش → فیلدهای زیر آن گزینه‌ها، و فیلد →
 * زیرفیلدهای همیشگی‌اش. دقیقاً همان مسیری که ویو درخت رندر می‌کند و ربات
 * صف سوال‌ها را از آن می‌سازد.
 *
 * هرچه در این پیمایش دیده نشود در هیچ‌جای اپ ظاهر نمی‌شود: والد آویزان،
 * نوادهٔ یک یتیم، یا حلقه‌ای که هرگز به ریشه نمی‌رسد.
 *
 * یک منبع حقیقت برای دستور fields:purge-orphans و هشدار صفحه‌ی فرم‌ساز.
 */
class FieldTree
{
    /**
     * @param Collection $fields  رکوردهایی با id/parent_option_id/parent_field_id
     * @param Collection $options رکوردهایی با id/field_id
     * @return array<int,bool> [fieldId => true] برای فیلدهای قابل دسترس
     */
    public static function reachableMap(Collection $fields, Collection $options): array
    {
        $optionsByField  = $options->groupBy('field_id');
        $childrenByOpt   = $fields->whereNotNull('parent_option_id')->groupBy('parent_option_id');
        $childrenByField = $fields->whereNotNull('parent_field_id')->groupBy('parent_field_id');

        $stack = $fields
            ->filter(fn ($f) => is_null($f->parent_option_id) && is_null($f->parent_field_id))
            ->pluck('id')
            ->all();

        $reachable = [];
        while ($stack) {
            $id = array_pop($stack);
            if (isset($reachable[$id])) continue;   // محافظ حلقه
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

        return $reachable;
    }

    /** فیلدهایی که در پیمایش دیده نشدند */
    public static function unreachable(Collection $fields, Collection $options): Collection
    {
        $reachable = self::reachableMap($fields, $options);
        return $fields->reject(fn ($f) => isset($reachable[$f->id]))->values();
    }
}
