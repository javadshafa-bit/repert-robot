<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\CategoryField;
use App\Models\FieldOption;
use App\Models\Province;
use App\Models\Setting;
use App\Models\Tenant;
use App\Services\TenantProvisioner;
use App\Support\BotText;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * فرم نظرسنجی «تئاتر فاطمیه» (خانه تئاتر آئینی حوزه هنری) را برای یک سازمان می‌سازد
 * و ربات آن سازمان را به حالت نظرسنجی می‌برد.
 *
 * دوباره اجرا کردنش امن است: اگر فرم با همین نام از قبل باشد، سؤال‌ها دست نمی‌خورند
 * (تا پاسخ‌های ثبت‌شده به فیلدهای قبلی وصل بمانند) و فقط تنظیمات هم‌گام می‌شوند.
 * بعد از ساخت، سؤال‌ها و گزینه‌ها از فرم‌ساز پنل قابل ویرایش‌اند.
 */
class SetupFatemiyehSurvey extends Command
{
    protected $signature = 'survey:fatemiyeh
                            {tenant : شناسه یا ایمیل سازمان}';

    protected $description = 'ساخت فرم نظرسنجی «تئاتر فاطمیه» و روشن کردن حالت نظرسنجی ربات یک سازمان';

    public const TITLE = 'تئاتر فاطمیه';

    /** [سؤال, [گزینه‌ها]] — همه چندگزینه‌ای و اجباری */
    public const QUESTIONS = [
        ['امشب در کدام تالار بودید؟', ['تالار اندیشه', 'تالار سوره مهر', 'تالار ماه']],
        ['کدام نمایش را دیدید؟', [
            'نقطه عطف', 'مرگ اندوه', 'خورشید پشت در', 'تنها نمی‌توانم',
            'آتش دل', 'ستاره بان', 'زنی شبیه مادر', 'نظاره گر',
        ]],
        ['نمایش امشب را چطور ارزیابی می‌کنید؟', ['عالی', 'خوب', 'متوسط', 'ضعیف']],
        ['پذیرایی و میزبانی چطور بود؟', ['عالی', 'خوب', 'متوسط', 'ضعیف']],
        ['از چه راهی با این برنامه آشنا شدید؟', ['دوستان و آشنایان', 'مسجد و هیئت', 'فضای مجازی', 'تبلیغات شهری', 'سایر']],
        ['رده‌ی سنی شما؟', ['زیر ۱۸ سال', '۱۸ تا ۳۰ سال', '۳۱ تا ۴۵ سال', '۴۶ تا ۶۰ سال', 'بالای ۶۰ سال']],
        ['دوست دارید از برنامه‌های بعدی حوزه هنری باخبر شوید؟', ['بله، حتماً', 'خیر']],
    ];

    public function handle(TenantProvisioner $provisioner): int
    {
        $key    = (string) $this->argument('tenant');
        $tenant = ctype_digit($key) ? Tenant::find((int) $key) : Tenant::where('email', $key)->first();

        if (!$tenant) {
            $this->error("سازمانی با شناسه/ایمیل «{$key}» پیدا نشد.");
            return self::FAILURE;
        }

        // نماینده بدون استان ساخته نمی‌شود
        $provisioner->seedProvinces($tenant);

        TenantContext::forTenant($tenant, function () use ($tenant) {
            $category = Category::where('name', self::TITLE)->first();

            if ($category) {
                $this->warn('فرم «' . self::TITLE . '» از قبل وجود دارد؛ سؤال‌ها دست نخوردند.');
                if (!$category->is_active) $category->update(['is_active' => true]);
            } else {
                $category = $this->createForm();
                $this->info('✓ فرم «' . self::TITLE . '» با ' . count(self::QUESTIONS) . ' سؤال ساخته شد.');
            }

            Setting::set('bot_survey_mode', '1');
            Setting::set('survey_category_id', (string) $category->id);
            if (!Setting::get('survey_repeat')) Setting::set('survey_repeat', 'nightly');

            // شرکت‌کنندگان در استان تهران ثبت شوند، نه اولین استان الفبایی
            $tehran = Province::where('name', 'تهران')->value('id');
            if ($tehran && !Setting::get('guest_province_id')) Setting::set('guest_province_id', (string) $tehran);

            BotText::flush();

            $this->info('✓ حالت نظرسنجی روشن شد (هر نفر هر شب یک بار).');
            $this->newLine();

            foreach ($category->fields()->whereNull('parent_option_id')->whereNull('parent_field_id')->orderBy('sort_order')->get() as $i => $field) {
                $this->line(($i + 1) . '. ' . $field->label . '  [' . $field->options()->pluck('label')->implode('، ') . ']');
            }

            $this->newLine();
            if (!$tenant->bot_token) {
                $this->warn('این سازمان هنوز توکن ربات ندارد: پنل ← تنظیمات ← توکن ربات ← اتصال.');
            } elseif ($tenant->bot_username) {
                $this->info('لینک ربات برای QR: https://ble.ir/' . $tenant->bot_username);
            }
        });

        return self::SUCCESS;
    }

    private function createForm(): Category
    {
        return DB::transaction(function () {
            $category = Category::create([
                'name'       => self::TITLE,
                'sort_order' => 0,
                'is_active'  => true,
            ]);

            foreach (self::QUESTIONS as $i => [$label, $options]) {
                $field = CategoryField::create([
                    'category_id' => $category->id,
                    'label'       => $label,
                    'type'        => 'option',
                    'is_required' => true,
                    'is_multiple' => false,
                    'sort_order'  => $i + 1,
                ]);

                foreach ($options as $j => $option) {
                    FieldOption::create([
                        'field_id'   => $field->id,
                        'label'      => $option,
                        'sort_order' => $j + 1,
                    ]);
                }
            }

            return $category;
        });
    }
}
