<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ساختار درختی سه دسته‌بندی:
 *   هنری   — طبق خواسته کاربر (3 لایه)
 *   تجسمی  — تا 5 لایه
 *   اجرایی — تا 3 لایه
 */
class TreeFieldsSeeder extends Seeder
{
    public function run(): void
    {
        // اگر قبلاً اجرا شده باشد، دوباره اجرا نشود
        if (DB::table('categories')->whereIn('name', ['هنری', 'تجسمی', 'اجرایی'])->exists()) {
            $this->command->warn('دسته‌بندی‌ها قبلاً ایجاد شده‌اند. Seeder رد شد.');
            return;
        }

        $honariId   = DB::table('categories')->insertGetId(['name' => 'هنری',   'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $tajasomiId = DB::table('categories')->insertGetId(['name' => 'تجسمی',  'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $ejraiiId   = DB::table('categories')->insertGetId(['name' => 'اجرایی', 'sort_order' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->buildHonari($honariId);
        $this->buildTajasomi($tajasomiId);
        $this->buildEjraii($ejraiiId);

        $this->command->info('✅ سه دسته‌بندی با ساختار درختی ایجاد شد.');
    }

    // ──────────────────────────────────────────────────────────────
    // کمکی: ایجاد یک فیلد
    // ──────────────────────────────────────────────────────────────
    private function field(int $catId, string $label, string $type, bool $multi = false, ?int $parentOptId = null, int $sort = 0): int
    {
        return DB::table('category_fields')->insertGetId([
            'category_id'      => $catId,
            'label'            => $label,
            'type'             => $type,
            'is_required'      => true,
            'is_multiple'      => $multi,
            'sort_order'       => $sort,
            'parent_option_id' => $parentOptId,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // کمکی: ایجاد یک گزینه برای فیلد
    // ──────────────────────────────────────────────────────────────
    private function option(int $fieldId, string $label, int $sort = 0): int
    {
        return DB::table('field_options')->insertGetId([
            'field_id'   => $fieldId,
            'label'      => $label,
            'sort_order' => $sort,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // هنری — 3 لایه
    //   لایه ۱: انتخاب نوع (گزینه‌های ۱ / ۲ / ۳)
    //   لایه ۲:
    //     ۱  → عکس‌های چندگانه (photo, multi)
    //     ۲  → انتخاب زیرگزینه (الف / ب)
    //     ۳  → ورودی متن
    //   لایه ۳:
    //     الف → لینک
    //     ب   → لینک
    // ══════════════════════════════════════════════════════════════
    private function buildHonari(int $catId): void
    {
        $root = $this->field($catId, 'انتخاب نوع', 'option', sort: 1);

        // گزینه ۱  →  عکس (چندگانه)
        $opt1 = $this->option($root, '۱', 1);
        $this->field($catId, 'عکس‌ها', 'photo', multi: true, parentOptId: $opt1, sort: 1);

        // گزینه ۲  →  زیرگزینه (الف / ب)
        $opt2    = $this->option($root, '۲', 2);
        $subOpt  = $this->field($catId, 'انتخاب زیرگزینه', 'option', parentOptId: $opt2, sort: 1);

        $optAlef = $this->option($subOpt, 'الف', 1);
        $this->field($catId, 'لینک', 'link', parentOptId: $optAlef, sort: 1);

        $optBe = $this->option($subOpt, 'ب', 2);
        $this->field($catId, 'لینک', 'link', parentOptId: $optBe, sort: 1);

        // گزینه ۳  →  متن
        $opt3 = $this->option($root, '۳', 3);
        $this->field($catId, 'توضیحات', 'text', parentOptId: $opt3, sort: 1);
    }

    // ══════════════════════════════════════════════════════════════
    // تجسمی — 5 لایه
    //   ل۱: رشته هنری          (نقاشی / مجسمه‌سازی / گرافیک)
    //   ل۲: سبک / ماده / نوع
    //   ل۳: تکنیک / ابزار / نرم‌افزار
    //   ل۴: ابعاد / سبک
    //   ل۵: تصویر یا لینک (برگ)
    // ══════════════════════════════════════════════════════════════
    private function buildTajasomi(int $catId): void
    {
        // ─── لایه ۱ ───────────────────────────────────────────────
        $l1 = $this->field($catId, 'رشته هنری', 'option', sort: 1);

        // ════ شاخه: نقاشی ════════════════════════════════════════
        $optNaghashi = $this->option($l1, 'نقاشی', 1);
        // لایه ۲
        $l2N = $this->field($catId, 'سبک نقاشی', 'option', parentOptId: $optNaghashi, sort: 1);

            // سنتی
            $optSonati = $this->option($l2N, 'سنتی', 1);
            // لایه ۳
            $l3S = $this->field($catId, 'تکنیک', 'option', parentOptId: $optSonati, sort: 1);

                // آبرنگ
                $oAb = $this->option($l3S, 'آبرنگ', 1);
                // لایه ۴
                $l4Ab = $this->field($catId, 'ابعاد اثر', 'option', parentOptId: $oAb, sort: 1);
                    $oAbK = $this->option($l4Ab, 'کوچک (زیر ۳۰ سانت)', 1);
                    $this->field($catId, 'تصویر اثر', 'photo', multi: true, parentOptId: $oAbK, sort: 1); // لایه ۵
                    $oAbB = $this->option($l4Ab, 'بزرگ (بالای ۳۰ سانت)', 2);
                    $this->field($catId, 'تصویر اثر', 'photo', multi: true, parentOptId: $oAbB, sort: 1); // لایه ۵

                // رنگ روغن
                $oRR = $this->option($l3S, 'رنگ روغن', 2);
                $l4RR = $this->field($catId, 'ابعاد اثر', 'option', parentOptId: $oRR, sort: 1);
                    $oRRK = $this->option($l4RR, 'کوچک (زیر ۵۰ سانت)', 1);
                    $this->field($catId, 'تصویر اثر', 'photo', multi: true, parentOptId: $oRRK, sort: 1);
                    $oRRB = $this->option($l4RR, 'بزرگ (بالای ۵۰ سانت)', 2);
                    $this->field($catId, 'تصویر اثر', 'photo', multi: true, parentOptId: $oRRB, sort: 1);

            // مدرن
            $optModern = $this->option($l2N, 'مدرن', 2);
            $l3M = $this->field($catId, 'تکنیک مدرن', 'option', parentOptId: $optModern, sort: 1);

                $oAk = $this->option($l3M, 'اکریلیک', 1);
                $l4Ak = $this->field($catId, 'ابعاد اثر', 'option', parentOptId: $oAk, sort: 1);
                    $oAkK = $this->option($l4Ak, 'کوچک', 1);
                    $this->field($catId, 'تصویر اثر', 'photo', multi: true, parentOptId: $oAkK, sort: 1);
                    $oAkB = $this->option($l4Ak, 'بزرگ', 2);
                    $this->field($catId, 'تصویر اثر', 'photo', multi: true, parentOptId: $oAkB, sort: 1);

                $oMx = $this->option($l3M, 'میکس مدیا', 2);
                $l4Mx = $this->field($catId, 'ابعاد اثر', 'option', parentOptId: $oMx, sort: 1);
                    $oMxK = $this->option($l4Mx, 'کوچک', 1);
                    $this->field($catId, 'تصویر اثر', 'photo', multi: true, parentOptId: $oMxK, sort: 1);
                    $oMxB = $this->option($l4Mx, 'بزرگ', 2);
                    $this->field($catId, 'تصویر اثر', 'photo', multi: true, parentOptId: $oMxB, sort: 1);

        // ════ شاخه: مجسمه‌سازی ══════════════════════════════════
        $optMoj = $this->option($l1, 'مجسمه‌سازی', 2);
        $l2Moj = $this->field($catId, 'ماده اولیه', 'option', parentOptId: $optMoj, sort: 1);

            // سنگ
            $oSang = $this->option($l2Moj, 'سنگ', 1);
            $l3Sang = $this->field($catId, 'تکنیک', 'option', parentOptId: $oSang, sort: 1);
                $oTrash = $this->option($l3Sang, 'تراش', 1);
                $l4Trash = $this->field($catId, 'ابعاد', 'option', parentOptId: $oTrash, sort: 1);
                    $oTK = $this->option($l4Trash, 'کوچک', 1);
                    $this->field($catId, 'تصویر مجسمه', 'photo', multi: true, parentOptId: $oTK, sort: 1);
                    $oTB = $this->option($l4Trash, 'بزرگ', 2);
                    $this->field($catId, 'تصویر مجسمه', 'photo', multi: true, parentOptId: $oTB, sort: 1);
                $oRikh = $this->option($l3Sang, 'ریخته‌گری', 2);
                $l4Rikh = $this->field($catId, 'ابعاد', 'option', parentOptId: $oRikh, sort: 1);
                    $oRK = $this->option($l4Rikh, 'کوچک', 1);
                    $this->field($catId, 'تصویر مجسمه', 'photo', multi: true, parentOptId: $oRK, sort: 1);
                    $oRB = $this->option($l4Rikh, 'بزرگ', 2);
                    $this->field($catId, 'تصویر مجسمه', 'photo', multi: true, parentOptId: $oRB, sort: 1);

            // چوب
            $oChob = $this->option($l2Moj, 'چوب', 2);
            $l3Chob = $this->field($catId, 'تکنیک', 'option', parentOptId: $oChob, sort: 1);
                $oManb = $this->option($l3Chob, 'منبت‌کاری', 1);
                $l4Manb = $this->field($catId, 'ابعاد', 'option', parentOptId: $oManb, sort: 1);
                    $oMK = $this->option($l4Manb, 'کوچک', 1);
                    $this->field($catId, 'تصویر مجسمه', 'photo', multi: true, parentOptId: $oMK, sort: 1);
                    $oMB = $this->option($l4Manb, 'بزرگ', 2);
                    $this->field($catId, 'تصویر مجسمه', 'photo', multi: true, parentOptId: $oMB, sort: 1);
                $oTark = $this->option($l3Chob, 'ترکیبی', 2);
                $l4Tark = $this->field($catId, 'ابعاد', 'option', parentOptId: $oTark, sort: 1);
                    $oTrK = $this->option($l4Tark, 'کوچک', 1);
                    $this->field($catId, 'تصویر مجسمه', 'photo', multi: true, parentOptId: $oTrK, sort: 1);
                    $oTrB = $this->option($l4Tark, 'بزرگ', 2);
                    $this->field($catId, 'تصویر مجسمه', 'photo', multi: true, parentOptId: $oTrB, sort: 1);

        // ════ شاخه: گرافیک ══════════════════════════════════════
        $optGraf = $this->option($l1, 'گرافیک', 3);
        $l2Graf = $this->field($catId, 'نوع گرافیک', 'option', parentOptId: $optGraf, sort: 1);

            // پوستر
            $oPoster = $this->option($l2Graf, 'پوستر', 1);
            $l3Poster = $this->field($catId, 'نرم‌افزار', 'option', parentOptId: $oPoster, sort: 1);
                $oPS = $this->option($l3Poster, 'فتوشاپ', 1);
                $l4PS = $this->field($catId, 'سبک طراحی', 'option', parentOptId: $oPS, sort: 1);
                    $oPSM = $this->option($l4PS, 'مینیمال', 1);
                    $this->field($catId, 'فایل نهایی (لینک)', 'link', parentOptId: $oPSM, sort: 1);
                    $oPSD = $this->option($l4PS, 'تفصیلی', 2);
                    $this->field($catId, 'فایل نهایی (لینک)', 'link', parentOptId: $oPSD, sort: 1);
                $oAI = $this->option($l3Poster, 'ایلوستریتور', 2);
                $l4AI = $this->field($catId, 'سبک طراحی', 'option', parentOptId: $oAI, sort: 1);
                    $oAIM = $this->option($l4AI, 'مینیمال', 1);
                    $this->field($catId, 'فایل نهایی (لینک)', 'link', parentOptId: $oAIM, sort: 1);
                    $oAID = $this->option($l4AI, 'تفصیلی', 2);
                    $this->field($catId, 'فایل نهایی (لینک)', 'link', parentOptId: $oAID, sort: 1);

            // لوگو
            $oLogo = $this->option($l2Graf, 'لوگو', 2);
            $l3Logo = $this->field($catId, 'نرم‌افزار', 'option', parentOptId: $oLogo, sort: 1);
                $oPSL = $this->option($l3Logo, 'فتوشاپ', 1);
                $l4PSL = $this->field($catId, 'سبک', 'option', parentOptId: $oPSL, sort: 1);
                    $oPSLM = $this->option($l4PSL, 'مینیمال', 1);
                    $this->field($catId, 'فایل نهایی (لینک)', 'link', parentOptId: $oPSLM, sort: 1);
                    $oPSLD = $this->option($l4PSL, 'تفصیلی', 2);
                    $this->field($catId, 'فایل نهایی (لینک)', 'link', parentOptId: $oPSLD, sort: 1);
                $oAIL = $this->option($l3Logo, 'ایلوستریتور', 2);
                $l4AIL = $this->field($catId, 'سبک', 'option', parentOptId: $oAIL, sort: 1);
                    $oAILM = $this->option($l4AIL, 'مینیمال', 1);
                    $this->field($catId, 'فایل نهایی (لینک)', 'link', parentOptId: $oAILM, sort: 1);
                    $oAILD = $this->option($l4AIL, 'تفصیلی', 2);
                    $this->field($catId, 'فایل نهایی (لینک)', 'link', parentOptId: $oAILD, sort: 1);
    }

    // ══════════════════════════════════════════════════════════════
    // اجرایی — 3 لایه
    //   ل۱: نوع اجرا       (موسیقی / تئاتر / رقص)
    //   ل۲: تخصص           (ساز / نوع نمایش / سبک رقص)
    //   ل۳: لینک اجرا       (برگ)
    // ══════════════════════════════════════════════════════════════
    private function buildEjraii(int $catId): void
    {
        $l1 = $this->field($catId, 'نوع اجرا', 'option', sort: 1);

        // ── موسیقی
        $oMoosi = $this->option($l1, 'موسیقی', 1);
        $l2Moosi = $this->field($catId, 'ساز', 'option', parentOptId: $oMoosi, sort: 1);
            foreach ([['تار', 1], ['سه‌تار', 2], ['کمانچه', 3], ['سنتور', 4]] as [$name, $s]) {
                $o = $this->option($l2Moosi, $name, $s);
                $this->field($catId, 'لینک اجرا', 'link', parentOptId: $o, sort: 1);
            }

        // ── تئاتر
        $oTheater = $this->option($l1, 'تئاتر', 2);
        $l2Theater = $this->field($catId, 'نوع نمایش', 'option', parentOptId: $oTheater, sort: 1);
            foreach ([['کمدی', 1], ['تراژدی', 2], ['موزیکال', 3]] as [$name, $s]) {
                $o = $this->option($l2Theater, $name, $s);
                $this->field($catId, 'لینک اجرا', 'link', parentOptId: $o, sort: 1);
            }

        // ── رقص
        $oRaqs = $this->option($l1, 'رقص', 3);
        $l2Raqs = $this->field($catId, 'سبک رقص', 'option', parentOptId: $oRaqs, sort: 1);
            foreach ([['ایرانی', 1], ['کلاسیک', 2], ['مدرن', 3]] as [$name, $s]) {
                $o = $this->option($l2Raqs, $name, $s);
                $this->field($catId, 'لینک اجرا', 'link', parentOptId: $o, sort: 1);
            }
    }
}
