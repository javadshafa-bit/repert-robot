# repert-robot — راهنمای پروژه برای Claude

## معرفی پروژه
سامانه گزارش‌گیری حوزه هنری — یک ربات تلگرام (Bale) با پنل ادمین Laravel.
کاربران نماینده از طریق ربات گزارش ثبت می‌کنند؛ مدیران از پنل وب داده‌ها را مدیریت می‌کنند.

## تکنولوژی
- **Backend:** Laravel 11 (PHP)
- **Frontend:** Blade + Tailwind CSS
- **ربات:** Bale Messenger API (مشابه Telegram) — endpoint: `POST /api/bot/webhook`
- **DB:** MySQL

## ساختار دایرکتوری‌های مهم

```
app/
  Http/Controllers/
    Admin/
      CategoryController.php   — فرم‌ساز و دسته‌بندی‌ها (+ فیلدها، گزینه‌ها)
      DepartmentController.php  — دپارتمان‌ها
      ReportController.php      — مشاهده گزارش‌ها
      RepresentativeController.php
      SettingController.php
      DashboardController.php
      UserController.php        — مدیریت کاربران ادمین
      RoleController.php        — نقش‌ها و دسترسی‌ها
      ExportController.php      — خروجی Excel
    Api/
      BotController.php         — منطق کامل ربات (webhook handler)
    Auth/
      LoginController.php
  Models/
    Category.php        — دسته‌بندی گزارش (is_active, sort_order)
    CategoryField.php   — فیلدهای هر دسته (type: text|option|photo|link)
    FieldOption.php     — گزینه‌های فیلد option
    Department.php      — دپارتمان (is_active)
    Representative.php  — نمایندگان (شناسه تلگرام)
    Report.php          — گزارش ثبت‌شده
    BotState.php        — وضعیت مکالمه ربات هر کاربر
    Setting.php         — تنظیمات سیستم (bot_token, ...)
    Province.php
    MonthlyStatus.php
    User.php            — کاربران ادمین (is_super_admin)
resources/
  views/admin/
    categories/         — index, create, edit, _tree_fragment
    departments/
    reports/
    representatives/
    settings/
    users/
    roles/
routes/
  web.php               — همه route‌ها اینجاست
database/migrations/    — تاریخچه کامل migrations
```

## مدل‌های داده — جدول‌های کلیدی

### categories
| ستون | نوع | توضیح |
|------|-----|-------|
| id | bigint | PK |
| name | string | نام دسته |
| sort_order | int | ترتیب نمایش |
| is_active | boolean | نمایش در ربات |

### category_fields
| ستون | نوع | توضیح |
|------|-----|-------|
| category_id | FK | |
| parent_option_id | FK nullable | زیرفیلد شرطی (زیر یک option) |
| parent_field_id | FK nullable | زیرفیلد همیشگی (always-child) |
| label | string | |
| description | string nullable | |
| type | enum | text, option, photo, link |
| is_required | boolean | |
| is_multiple | boolean | آپلود چندتایی |
| sort_order | int | |

### bot_states
| ستون | توضیح |
|------|-------|
| chat_id | شناسه چت ربات |
| step | مرحله جاری: idle, selecting_month, selecting_department, selecting_category, answering_field, editing_field, confirming |
| representative_id | نماینده احراز هویت شده |
| department_id | دپارتمان انتخابی |
| category_id | دسته انتخابی |
| jalali_month | ماه جلالی |
| draft_data | JSON — پاسخ‌های در حال پر شدن |
| field_queue | JSON — صف فیلدهای باقیمانده |
| last_message_id | آخرین پیام ربات (برای حذف) |

## جریان ربات (BotController)

1. `/start` → احراز هویت با شماره تلفن (contact)
2. منوی اصلی → ثبت گزارش جدید
3. Flow: انتخاب ماه → دپارتمان → دسته‌بندی → پر کردن فیلدها یکی‌یکی
4. پیش‌نمایش → تأیید/ویرایش → ذخیره در `reports`

فیلدهای `option` → inline keyboard برای انتخاب  
فیلدهای `text/photo/link` → پیام متنی/فایل  
زیرفیلدهای شرطی (`parent_option_id`) → بعد از انتخاب option اضافه می‌شوند  
زیرفیلدهای همیشگی (`parent_field_id`) → همیشه بعد از فیلد والد  

## سیستم دسترسی ادمین
- `User` has many `Role`s
- هر `Role` دارای آرایه `permissions` است (مثل `categories`, `reports`, `users`)
- Middleware `admin.can:X` بررسی می‌کند
- `is_super_admin` همه دسترسی‌ها را دارد

## نکات مهم
- ربات فقط دسته‌بندی‌هایی را نشان می‌دهد که `is_active = true` باشند
- دپارتمان‌ها هم `is_active` دارند
- تنظیمات bot از جدول `settings` با `Setting::get('key')` خوانده می‌شود
- API ربات: `https://tapi.bale.ai/bot{token}/`
- فایل‌های آپلودشده در `storage` ذخیره می‌شوند

## روند deploy (push به GitHub + سرور چابکان)

### ۱. push به GitHub (از محیط local — PowerShell)
```powershell
cd "F:\پروژه ها\repert-robot"
git add .
git commit -m "توضیح تغییر"
git push origin main
```
- remote: `https://github.com/javadshafa-bit/repert-robot.git`

### ۲. deploy روی سرور چابکان
پس از push، روی سرور چابکان اسکریپت `deploy.sh` اجرا می‌شود:
```bash
bash deploy.sh
```

مراحل داخل `deploy.sh`:
1. `git pull origin main` — دریافت کد جدید
2. `composer install --no-dev --optimize-autoloader` — وابستگی‌های PHP
3. `npm ci && npm run build` — build assets
4. پاک کردن cache‌ها (`config:clear`, `route:clear`, `view:clear`, `cache:clear`)
5. `php artisan migrate --force` — اجرای migration‌های جدید
6. کش برای production (`config:cache`, `route:cache`, `view:cache`)
7. پاک کردن bot_states نیمه‌کاره (در صورت تغییر فرمت داده)
8. `php artisan storage:link`

### نکته
- آدرس پنل ادمین روی سرور: `https://laravel-noejus.chbkn.run/admin`
- نام پروژه در چابکان: `laravel-noejus` (طبق `chabok.json`)
