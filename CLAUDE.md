# repert-robot — راهنمای پروژه برای Claude

## معرفی پروژه
سامانه گزارش‌گیری حوزه هنری — یک ربات تلگرام (Bale) با پنل ادمین Laravel.
کاربران نماینده از طریق ربات گزارش ثبت می‌کنند؛ مدیران از پنل وب داده‌ها را مدیریت می‌کنند.

## تکنولوژی
- **Backend:** Laravel 13 (PHP)
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
    Platform/                   — پنل سوپرادمین (فقط نظارت)
      DashboardController.php
      TenantController.php      — لیست/جزئیات، تعلیق، تعیین دستی اشتراک
      TenantMonitorController.php — صفحات فقط‌خواندنی داده‌ی یک سازمان
      DiscountCodeController.php
      PlatformSettingController.php
    BillingController.php       — اشتراک، پرداخت، callback، رسید
    Auth/
      LoginController.php
  Services/
    BotConnector.php            — setWebhook/deleteWebhook هر سازمان
    SubscriptionService.php     — تنها جای تغییر دوره‌ی اشتراک (+ لاگ + هم‌گامی ربات)
    TenantProvisioner.php
    Payment/
      ZarinpalGateway.php       — API v4 زرین‌پال
      PriceCalculator.php       — مبلغ/روز/تخفیف، همیشه سمت سرور
      PaymentProcessor.php      — start/complete (ایدمپوتنت)
  Support/
    TenantContext.php           — مستأجر جاری
    TenantRule.php              — exists/unique مستأجرمحور
    JalaliDate.php              — تبدیل تاریخ شمسی فرم‌ها
  Models/
    Tenant.php          — سازمان، وضعیت، اشتراک، ربات
    Payment.php         — پرداخت (تومان)
    DiscountCode.php    — کد تخفیف سراسری
    PlatformSetting.php — تنظیمات پلتفرم (key/value)
    SubscriptionLog.php — تاریخچه‌ی تغییر دوره
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

## چندمستأجری (Multi-tenant)
سامانه SaaS است: هر سازمان یک رکورد در `tenants` دارد با ربات، داده و پنل مستقل.

- **ثبت‌نام:** `/register` → tenant با وضعیت `pending_payment` ساخته می‌شود و کاربر
  **بلافاصله لاگین می‌شود** و به `/billing` می‌رود. تایید دستی سوپرادمین وجود ندارد.
- **ایزوله‌سازی:** trait `App\Models\Concerns\BelongsToTenant` روی همه‌ی مدل‌های داده‌ای
  (به‌جز `User`، `Tenant`، `DiscountCode` و `PlatformSetting`) global scope روی `tenant_id`
  می‌گذارد و هنگام ساخت آن را پر می‌کند.
  مستأجر جاری در `App\Support\TenantContext` نگه‌داری می‌شود؛ اگر ست نشده باشد از
  `Auth::user()->tenant_id` خوانده می‌شود (چون route model binding قبل از میدلور `tenant` اجرا می‌شود).
  بدون مستأجر مشخص، کوئری‌ها هیچ رکوردی برنمی‌گردانند و **ساخت رکورد استثنا می‌دهد — حتی داخل
  `withoutScope()`** مگر `tenant_id` صریح داده شود. `withoutScope()` فقط برای خواندن است.
- **کوئری خام:** `DB::table(...)` از global scope عبور می‌کند — حتماً دستی `where('tenant_id', ...)` بزن.
  همین‌طور قوانین `exists`/`unique` در validation → از `App\Support\TenantRule` استفاده کن.
- **ربات per-tenant:** مسیر وبهوک `POST /api/bot/webhook/{tenant:webhook_secret}`؛
  توکن از `tenants.bot_token` خوانده می‌شود، نه از جدول `settings`.
  اتصال/قطع اتصال در `App\Services\BotConnector`. سقف درخواست با limiter نام‌دار
  `bot-webhook` و کلیدِ **مستأجر** اعمال می‌شود، نه IP.
- **مسیر سازگاری موقت:** `POST /api/bot/webhook` (بدون secret) به سازمان پیش‌فرض وصل است
  تا ربات‌های ثبت‌شده روی آدرس قدیمی نخوابند. **بعد از اجرای موفق `tenants:refresh-webhook`
  روی سرور، بلوک `bot.webhook.legacy` از `routes/web.php` حذف شود.**
- **دستورهای کمکی:** `tenants:create-platform-admin`، `tenants:refresh-webhook`
  (اگر `APP_URL` عمومی و https نباشد خودش را متوقف می‌کند)، `tenants:seed-tree {tenant}`،
  `db:import-sqlite {path} --tenant=N`، `subscriptions:expire`.

## اشتراک و پرداخت
- **وضعیت‌های `tenants.status`:**

  | وضعیت | لاگین پنل | ربات |
  |---|---|---|
  | `pending_payment` | فقط `/billing` | ❌ |
  | `active` | کامل | ✅ |
  | `expired` | فقط `/billing` | ❌ |
  | `suspended` | ❌ (logout) | ❌ |

- منبع حقیقت `Tenant::hasActiveSubscription()` است؛ ستون `status` فقط بازتاب ایندکس‌پذیر آن
  است و با `refreshStatus()` (در میدلور `ResolveTenant` و کرون `subscriptions:expire`) هم‌گام می‌شود.
  `botIsUsable()` هم همین را چک می‌کند، پس **با تمام شدن اشتراک ربات هم می‌خوابد**.
- **گیت اشتراک در میدلور است، نه در لاگین.** کاربر پرداخت‌نکرده باید بتواند وارد شود
  وگرنه هرگز به صفحه‌ی پرداخت نمی‌رسد. فقط `suspended` جلوی ورود گرفته می‌شود.
- **قیمت‌گذاری:** `platform_settings.price_per_day` (تومان). کاربر یا تعداد روز می‌دهد یا
  مبلغ دلخواه؛ **تعداد روز از مبلغ پیش از تخفیف حساب می‌شود** تا کد تخفیف روزها را کم نکند.
- **درگاه:** `App\Services\Payment\ZarinpalGateway` (API v4، sandbox پشت فلگ
  `platform_settings.zarinpal_sandbox`). مبالغ در دیتابیس **تومان**‌اند و لحظه‌ی ارسال ×۱۰ می‌شوند.
- **قواعدی که نباید شکسته شوند:** مبلغ فقط از رکورد `Payment` خوانده می‌شود؛ فعال‌سازی فقط
  بعد از `verify` موفق سمت سرور (نه `Status=OK`)؛ callback با `lockForUpdate` ایدمپوتنت است؛
  `payments.authority` یکتاست؛ مصرف کد تخفیف اتمیک و فقط پس از پرداخت موفق؛ `merchant_id`
  هیچ‌وقت در `raw_response` ذخیره نمی‌شود.
- **تمدید:** `App\Services\SubscriptionService` تنها جایی است که دوره عوض می‌شود — هر تغییر
  در `subscription_logs` ثبت می‌شود و ربات هم‌گام می‌شود (غیرفعال → فقط `deleteWebhook`،
  فعال دوباره → `setWebhook`). **توکن ربات هیچ‌وقت پاک نمی‌شود.**

## پنل پلتفرم (سوپرادمین)
`/platform/*` با میدلور `platform.admin` — فقط کاربران `is_platform_admin` (که `tenant_id = null` دارند).

- **می‌تواند:** داشبورد و آمار، لیست/جزئیات سازمان‌ها، صفحات نظارتی فقط‌خواندنی
  (نمایندگان، دپارتمان‌ها، دسته‌بندی‌ها، گزارش‌ها، پیام همگانی، پرداخت‌ها، تاریخچه‌ی اشتراک)،
  تعیین دستی دوره‌ی اشتراک، تعلیق/رفع تعلیق، CRUD کد تخفیف، تنظیمات پلتفرم.
- **نمی‌تواند و نباید بتواند:** ورود به `/admin`، دیدن یا وارد کردن `bot_token`،
  اتصال ربات، ویرایش داده‌ی کسب‌وکاری مستأجرها.
- **قاعده‌ی خواندن داده‌ی مستأجر:** فقط `TenantContext::forTenant()` داخل closure —
  **هرگز `withoutScope()` در کنترلرهای `Platform`**. برای آمار بین‌سازمانی از `DB::table` استفاده کن.
  میدلور `platform.admin` قبل و بعد از درخواست `TenantContext::forget()` می‌زند تا
  مستأجری برای کل طول درخواست ست نماند.
- داده‌ی سازمان منقضی حذف نمی‌شود؛ بعد از ۶ ماه فقط در داشبورد و خروجی `subscriptions:expire`
  به‌عنوان کاندیدای حذف **دستی** گزارش می‌شود.

## سیستم دسترسی ادمین
- `User` has many `Role`s
- هر `Role` دارای آرایه `permissions` است (مثل `categories`, `reports`, `users`)
- Middleware `admin.can:X` بررسی می‌کند
- `is_super_admin` یعنی دسترسی کامل **در محدوده‌ی همان سازمان** (نه پلتفرم)

## نکات مهم
- ربات فقط دسته‌بندی‌هایی را نشان می‌دهد که `is_active = true` باشند
- دپارتمان‌ها هم `is_active` دارند
- تنظیمات هر سازمان از جدول `settings` با `Setting::get('key')` خوانده می‌شود (per-tenant)
- توکن ربات از `tenants.bot_token` خوانده می‌شود، نه از `settings`
- تنظیمات پلتفرم (قیمت، درگاه) در `platform_settings` است — با `settings` قاطی نشود
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

### deploy روی سرور داکری (traefik) — محیط زنده‌ی فعلی
روی این سرور `deploy.sh` استفاده نمی‌شود. deploy یعنی build دوباره‌ی ایمیج:

```bash
cd /path/to/repert-robot
bash backup-docker.sh          # ۱) بکاپ — قبل از هر چیز
git pull origin main           # ۲) کد جدید
docker compose build           # ۳) ساخت ایمیج
docker compose up -d           # ۴) بالا آوردن (migrate خودکار اجرا می‌شود)
docker compose logs -f app     # ۵) تماشای خروجی migration
```

⚠️ **`docker/php/entrypoint.sh` هنگام بالا آمدن کانتینر `app` خودش
`php artisan migrate --force` می‌زند.** یعنی به‌محض `up -d` اسکیما عوض می‌شود؛
بکاپ باید *قبل* از آن کامل شده باشد.

کارهای بعد از بالا آمدن:
```bash
docker exec -it repert-app php artisan tenants:create-platform-admin owner@example.com --name="سوپرادمین"
docker exec -it repert-app php artisan tenants:refresh-webhook
```

بازگردانی در صورت خرابی:
```bash
gunzip -c backups/db-<زمان>.sql.gz | docker exec -i repert-mysql sh -c 'exec mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
git checkout <کامیت-قبلی> && docker compose build && docker compose up -d
```

**سرویس `scheduler`:** بدون آن هیچ کار زمان‌بندی‌شده‌ای اجرا نمی‌شود
(`broadcast:send-due` و `subscriptions:expire`). در `docker-compose.yml` تعریف شده و
`php artisan schedule:work` را دائم اجرا می‌کند — جایگزین فایل `cron-jobs` که مال چابکان است.

### ۳. کارهای یک‌باره بعد از اولین deploy چندمستأجری
```bash
# ۱) ساخت حساب سوپرادمین پلتفرم (اگر هنوز ساخته نشده)
php artisan tenants:create-platform-admin owner@example.com --name="سوپرادمین"

# ۲) ثبت دوباره‌ی وبهوک هر سازمان روی آدرس اختصاصی خودش.
#    تا این اجرا نشود، همه‌ی پیام‌ها از مسیر سازگاری قدیمی به سازمان پیش‌فرض می‌رود.
#    اگر APP_URL سرور عمومی و https نباشد، خود دستور متوقف می‌شود.
php artisan tenants:refresh-webhook
```
سپس:
- بلوک `bot.webhook.legacy` را از `routes/web.php` حذف کن و دوباره deploy کن.
- از `/platform/settings` قیمت هر روز، حداقل مبلغ و `merchant_id` زرین‌پال را تنظیم کن
  و تیک sandbox را بردار (تا وقتی تیک هست هیچ پرداخت واقعی‌ای انجام نمی‌شود).

### نکته
- آدرس پنل ادمین روی سرور: `https://laravel-noejus.chbkn.run/admin`
- نام پروژه در چابکان: `laravel-noejus` (طبق `chabok.json`)
