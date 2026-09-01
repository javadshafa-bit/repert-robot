<?php

namespace App\Support;

use App\Models\Setting;

/**
 * کاتالوگ متن‌های ربات.
 *
 * هر متنی که کاربر نهایی در بله می‌بیند اینجا یک کلید دارد: متن پیش‌فرض،
 * برچسب فارسی برای داشبورد، و متغیرهای مجازش. مدیر سازمان می‌تواند هر کدام
 * را از صفحه‌ی «متن‌های ربات» بازنویسی کند؛ هرچه بازنویسی نشده باشد،
 * مقدار پیش‌فرض همین فایل استفاده می‌شود.
 *
 * مقادیر در جدول settings (per-tenant) با همان نام کلید ذخیره می‌شوند،
 * بنابراین welcome_message و error_message که از قبل وجود داشتند حفظ می‌شوند.
 */
class BotText
{
    /** کش هر درخواست: [tenantId => [key => value]] */
    private static array $cache = [];

    /**
     * ساختار: گروه => ['label' => ..., 'items' => [key => [label, default, vars, rows]]]
     *
     * vars: متغیرهای مجاز آن متن (بدون آکولاد)
     * rows: تعداد خط textarea در داشبورد (۱ یعنی input تک‌خطی)
     */
    public static function catalog(): array
    {
        return [
            'auth' => [
                'label' => 'شروع و احراز هویت',
                'items' => [
                    'welcome_message' => [
                        'label'   => 'پیام خوش‌آمد (قبل از احراز هویت)',
                        'default' => 'به ربات گزارش‌دهی خوش آمدید.',
                        'vars'    => [],
                        'rows'    => 3,
                    ],
                    'btn_share_contact' => [
                        'label'   => 'دکمه ارسال شماره تماس',
                        'default' => '📱 ارسال شماره تماس (جهت احراز هویت)',
                        'vars'    => [],
                        'rows'    => 1,
                    ],
                    'auth_success' => [
                        'label'   => 'پیام بعد از احراز هویت موفق',
                        'default' => 'احراز هویت موفق. سلام {name} عزیز از استان {province}!',
                        'vars'    => ['name', 'family', 'fullname', 'province'],
                        'rows'    => 2,
                    ],
                    'greeting_returning' => [
                        'label'   => 'سلام به کاربر شناخته‌شده (start مجدد)',
                        'default' => 'سلام {name} عزیز از استان {province}!',
                        'vars'    => ['name', 'family', 'fullname', 'province'],
                        'rows'    => 2,
                    ],
                    'btn_skip_contact' => [
                        'label'   => 'دکمه رد کردن ارسال شماره (حالت اختیاری)',
                        'default' => 'فعلاً نمی‌خواهم شماره بدهم',
                        'vars'    => [],
                        'rows'    => 1,
                    ],
                    'err_need_contact' => [
                        'label'   => 'وقتی به‌جای دکمه، متن فرستاده شود',
                        'default' => 'برای ادامه لطفاً از دکمه‌ی پایین صفحه استفاده کنید.',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                    'greeting_open_access' => [
                        'label'   => 'سلام اولیه در حالت دسترسی آزاد',
                        'default' => 'سلام {name} عزیز! 👋',
                        'vars'    => ['name', 'family', 'fullname', 'province'],
                        'rows'    => 2,
                    ],
                    'error_message' => [
                        'label'   => 'پیام خطای عدم دسترسی / شماره ناشناس',
                        'default' => 'شما مجاز به استفاده از این ربات نیستید.',
                        'vars'    => [],
                        'rows'    => 3,
                    ],
                ],
            ],

            'menu' => [
                'label' => 'منوی اصلی',
                'items' => [
                    'main_menu_prompt' => [
                        'label'   => 'متن منوی اصلی',
                        'default' => 'لطفاً یک گزینه را انتخاب کنید:',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                    'btn_new_report' => [
                        'label'   => 'دکمه ارسال گزارش جدید',
                        'default' => '📝 ارسال گزارش جدید',
                        'vars'    => [],
                        'rows'    => 1,
                    ],
                    'warn_finish_current' => [
                        'label'   => 'هشدار: گزارش نیمه‌کاره دارد',
                        'default' => '⚠️ ابتدا گزارش جاری را تکمیل کنید.',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                ],
            ],

            'flow' => [
                'label' => 'مراحل گزارش',
                'items' => [
                    'ask_month' => [
                        'label'   => 'سوال انتخاب ماه',
                        'default' => 'ماه گزارش را انتخاب کنید:',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                    'btn_month_item' => [
                        'label'   => 'متن دکمه هر ماه',
                        'default' => 'گزارش {month}',
                        'vars'    => ['month'],
                        'rows'    => 1,
                    ],
                    'ask_department' => [
                        'label'   => 'سوال انتخاب دپارتمان',
                        'default' => 'دپارتمان مربوطه را انتخاب کنید:',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                    'ask_category' => [
                        'label'   => 'سوال انتخاب نوع گزارش',
                        'default' => 'نوع گزارش را انتخاب کنید:',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                    'empty_departments' => [
                        'label'   => 'وقتی هیچ دپارتمان فعالی نیست',
                        'default' => 'هیچ دپارتمان فعالی وجود ندارد!',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                    'empty_categories' => [
                        'label'   => 'وقتی هیچ دسته‌بندی فعالی نیست',
                        'default' => 'هیچ دسته‌بندی فعالی وجود ندارد!',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                    'btn_skip_field' => [
                        'label'   => 'دکمه رد کردن سوال غیراجباری',
                        'default' => '(خالی)',
                        'vars'    => [],
                        'rows'    => 1,
                    ],
                    'btn_back' => [
                        'label'   => 'دکمه بازگشت',
                        'default' => '🔙 بازگشت',
                        'vars'    => [],
                        'rows'    => 1,
                    ],
                    'btn_cancel' => [
                        'label'   => 'دکمه انصراف (مرحله اول)',
                        'default' => '🔙 انصراف',
                        'vars'    => [],
                        'rows'    => 1,
                    ],
                ],
            ],

            'fields' => [
                'label' => 'پرسش فیلدهای فرم',
                'items' => [
                    'field_prompt_header' => [
                        'label'   => 'سرتیتر سوال متنی/عکس/لینک',
                        'default' => 'لطفاً پاسخ دهید:',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                    'field_option_header' => [
                        'label'   => 'سرتیتر سوال چندگزینه‌ای',
                        'default' => 'لطفاً یکی را انتخاب کنید:',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                    'hint_photo_multiple' => [
                        'label'   => 'راهنمای عکس چندتایی',
                        'default' => '(عکس‌ها را یکی یکی ارسال کنید — وقتی تمام شد دکمه پایان را بزنید)',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                    'hint_photo_single' => [
                        'label'   => 'راهنمای عکس تکی',
                        'default' => '(لطفاً *عکس* مربوطه را ارسال کنید)',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                    'hint_link' => [
                        'label'   => 'راهنمای لینک',
                        'default' => '(لینک را با فرمت `https://...` ارسال کنید)',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                    'multiple_progress' => [
                        'label'   => 'پیام شمارش آیتم‌های دریافت‌شده',
                        'default' => "✅ تاکنون *{count} {type}* دریافت شد.\n\nمی‌توانید {type} دیگری ارسال کنید یا دکمه پایان را بزنید.",
                        'vars'    => ['count', 'type'],
                        'rows'    => 3,
                    ],
                    'btn_multiple_done' => [
                        'label'   => 'دکمه پایان ارسال این بخش',
                        'default' => '✅ پایان ارسال این بخش — مرحله بعدی',
                        'vars'    => [],
                        'rows'    => 1,
                    ],
                ],
            ],

            'date' => [
                'label' => 'انتخاب تاریخ (تقویم)',
                'items' => [
                    'date_pick_year' => [
                        'label'   => 'سوال مرحله انتخاب سال',
                        'default' => '📅 سال مورد نظر خود را انتخاب کنید',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                    'date_pick_month' => [
                        'label'   => 'سوال مرحله انتخاب ماه',
                        'default' => '📅 ماه مورد نظر خود را انتخاب کنید',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                    'date_pick_day' => [
                        'label'   => 'سوال مرحله انتخاب روز',
                        'default' => '📅 روز مورد نظر خود را انتخاب کنید',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                    'date_chosen_year' => [
                        'label'   => 'خط تأیید سال (در مراحل بعد می‌ماند)',
                        'default' => '✅ سال انتخابی شما: {year}',
                        'vars'    => ['year'],
                        'rows'    => 1,
                    ],
                    'date_chosen_month' => [
                        'label'   => 'خط تأیید ماه (در مرحله روز می‌ماند)',
                        'default' => '✅ ماه انتخابی شما: {month}',
                        'vars'    => ['month'],
                        'rows'    => 1,
                    ],
                ],
            ],

            'preview' => [
                'label' => 'پیش‌نمایش و ثبت نهایی',
                'items' => [
                    'preview_title' => [
                        'label'   => 'عنوان پیش‌نمایش',
                        'default' => '📄 *پیش‌نمایش گزارش شما*',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                    'preview_month_label' => [
                        'label'   => 'برچسب ماه در پیش‌نمایش',
                        'default' => 'ماه',
                        'vars'    => [],
                        'rows'    => 1,
                    ],
                    'preview_category_label' => [
                        'label'   => 'برچسب دسته در پیش‌نمایش',
                        'default' => 'دسته',
                        'vars'    => [],
                        'rows'    => 1,
                    ],
                    'preview_branch_label' => [
                        'label'   => 'برچسب مسیر انتخاب‌شده',
                        'default' => '🔀 *مسیر انتخاب‌شده:*',
                        'vars'    => [],
                        'rows'    => 1,
                    ],
                    'photo_uploaded_count' => [
                        'label'   => 'نمایش تعداد عکس در پیش‌نمایش',
                        'default' => '{count} عکس آپلود شد',
                        'vars'    => ['count'],
                        'rows'    => 1,
                    ],
                    'skipped_value' => [
                        'label'   => 'مقداری که برای سوال رد‌شده ثبت می‌شود',
                        'default' => '(خالی)',
                        'vars'    => [],
                        'rows'    => 1,
                    ],
                    'photo_uploaded_single' => [
                        'label'   => 'نمایش عکس تکی در پیش‌نمایش',
                        'default' => '[عکس آپلود شد]',
                        'vars'    => [],
                        'rows'    => 1,
                    ],
                    'btn_confirm' => [
                        'label'   => 'دکمه تایید و ارسال نهایی',
                        'default' => '✅ تایید و ارسال نهایی',
                        'vars'    => [],
                        'rows'    => 1,
                    ],
                    'btn_request_edit' => [
                        'label'   => 'دکمه ویرایش پاسخ‌ها',
                        'default' => '✏️ ویرایش پاسخ‌ها',
                        'vars'    => [],
                        'rows'    => 1,
                    ],
                    'ask_which_edit' => [
                        'label'   => 'سوال انتخاب بخش برای ویرایش',
                        'default' => 'کدام بخش را می‌خواهید ویرایش کنید؟',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                    'btn_edit_item' => [
                        'label'   => 'متن دکمه ویرایش هر فیلد',
                        'default' => 'ویرایش: {label}',
                        'vars'    => ['label'],
                        'rows'    => 1,
                    ],
                    'edit_done' => [
                        'label'   => 'پیام ویرایش موفق',
                        'default' => '✅ ویرایش انجام شد.',
                        'vars'    => [],
                        'rows'    => 1,
                    ],
                    'file_edit_done' => [
                        'label'   => 'پیام ویرایش فایل',
                        'default' => '✅ فایل ویرایش شد.',
                        'vars'    => [],
                        'rows'    => 1,
                    ],
                    'report_saved' => [
                        'label'   => 'پیام ثبت موفق گزارش',
                        'default' => '🎉 گزارش شما با موفقیت ثبت شد!',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                ],
            ],

            'errors' => [
                'label' => 'پیام‌های خطا',
                'items' => [
                    'err_need_photo' => [
                        'label'   => 'خطا: باید عکس بفرستد',
                        'default' => '⚠️ برای این فیلد باید عکس ارسال کنید.',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                    'err_need_photo_edit' => [
                        'label'   => 'خطا: برای ویرایش باید عکس بفرستد',
                        'default' => '⚠️ برای ویرایش این فیلد باید عکس ارسال کنید.',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                    'err_need_text' => [
                        'label'   => 'خطا: این فیلد پاسخ متنی می‌خواهد',
                        'default' => '⚠️ این فیلد نیاز به پاسخ متنی دارد.',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                    'err_invalid_link' => [
                        'label'   => 'خطا: لینک نامعتبر',
                        'default' => "⚠️ لینک معتبر نیست.\nمثال: `https://example.com`",
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                    'err_upload_failed' => [
                        'label'   => 'خطا: آپلود ناموفق',
                        'default' => '⚠️ خطایی در آپلود فایل رخ داد. دوباره تلاش کنید.',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                    'err_need_at_least_one' => [
                        'label'   => 'خطا: حداقل یک آیتم لازم است',
                        'default' => '⚠️ لطفاً حداقل یک {type} ارسال کنید.',
                        'vars'    => ['type'],
                        'rows'    => 2,
                    ],
                    'err_use_calendar' => [
                        'label'   => 'خطا: تاریخ باید از تقویم انتخاب شود',
                        'default' => '⚠️ برای این فیلد تاریخ را از تقویم بالا انتخاب کنید، نه با تایپ کردن.',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                    'err_invalid_date' => [
                        'label'   => 'خطا: تاریخ نامعتبر یا خارج از محدوده',
                        'default' => '⚠️ این تاریخ معتبر نیست. لطفاً دوباره از تقویم انتخاب کنید.',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                    'err_field_required' => [
                        'label'   => 'خطا: این فیلد اجباری است',
                        'default' => '⚠️ این فیلد اجباری است و نمی‌توان خالی گذاشت.',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                    'err_option_invalid' => [
                        'label'   => 'خطا: گزینه نامعتبر',
                        'default' => '⚠️ این گزینه دیگر معتبر نیست، ادامه می‌دهیم.',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                    'err_field_invalid' => [
                        'label'   => 'خطا: فیلد نامعتبر',
                        'default' => '⚠️ این فیلد دیگر معتبر نیست.',
                        'vars'    => [],
                        'rows'    => 2,
                    ],
                    'info_edited_message' => [
                        'label'   => 'وقتی کاربر پیامش را در مرحله تأیید ویرایش می‌کند',
                        'default' => '✏️ پیام شما ویرایش شد. برای اعمال تغییر، گزینه «ویرایش» را از منوی تأیید انتخاب کنید.',
                        'vars'    => [],
                        'rows'    => 3,
                    ],
                    'err_edit_not_supported' => [
                        'label'   => 'وقتی ویرایش پیام در این مرحله معنا ندارد',
                        'default' => '⚠️ ویرایش پیام در این مرحله قابل پردازش نیست. پیام جدیدی ارسال کنید.',
                        'vars'    => [],
                        'rows'    => 3,
                    ],
                ],
            ],

            'types' => [
                'label' => 'نام نوع فیلدها (جایگزین {type} در پیام‌ها)',
                'items' => [
                    'type_photo' => ['label' => 'نام نوع عکس',      'default' => 'عکس',  'vars' => [], 'rows' => 1],
                    'type_link'  => ['label' => 'نام نوع لینک',     'default' => 'لینک', 'vars' => [], 'rows' => 1],
                    'type_item'  => ['label' => 'نام نوع پیش‌فرض', 'default' => 'آیتم', 'vars' => [], 'rows' => 1],
                    'type_date'  => ['label' => 'نام نوع تاریخ',    'default' => 'تاریخ', 'vars' => [], 'rows' => 1],
                ],
            ],
        ];
    }

    /** [key => تعریف کلید] بدون گروه‌بندی */
    public static function definitions(): array
    {
        $flat = [];
        foreach (self::catalog() as $group) {
            foreach ($group['items'] as $key => $def) {
                $flat[$key] = $def;
            }
        }
        return $flat;
    }

    /** [key => متن پیش‌فرض] */
    public static function defaults(): array
    {
        return array_map(fn($def) => $def['default'], self::definitions());
    }

    /** [key => متن فعلی] — بازنویسی سازمان، وگرنه پیش‌فرض */
    public static function all(): array
    {
        $tenantId = TenantContext::id() ?? 0;

        if (!isset(self::$cache[$tenantId])) {
            $defaults = self::defaults();
            $stored   = Setting::whereIn('key', array_keys($defaults))->pluck('value', 'key')->all();

            $merged = [];
            foreach ($defaults as $key => $default) {
                $value = $stored[$key] ?? null;
                // رشته‌ی خالی یعنی «چیزی ننوشته» → پیش‌فرض
                $merged[$key] = ($value === null || trim($value) === '') ? $default : $value;
            }
            self::$cache[$tenantId] = $merged;
        }

        return self::$cache[$tenantId];
    }

    /**
     * متن یک کلید با جای‌گذاری متغیرها.
     *
     * BotText::get('auth_success', ['name' => 'علی', 'province' => 'تهران'])
     */
    public static function get(string $key, array $vars = []): string
    {
        $text = self::all()[$key] ?? (self::defaults()[$key] ?? '');

        if ($vars) {
            $replace = [];
            foreach ($vars as $name => $value) {
                $replace['{' . $name . '}'] = (string) $value;
            }
            $text = strtr($text, $replace);
        }

        return $text;
    }

    /** بعد از ذخیره در داشبورد، کش این درخواست باید دور ریخته شود */
    public static function flush(): void
    {
        self::$cache = [];
    }
}
