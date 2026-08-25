# پرامپت اجرایی: تبدیل «repert-robot» به سامانه چندمستأجری (Multi-tenant SaaS)

> **نحوه استفاده:** این فایل را کامل کپی کن و به همون ابزار کدنویسی که روی این پروژه کار می‌کنه بده (Codex / Claude Code / هر عامل دیگری که به کد و ترمینال پروژه دسترسی داره). قبل از هر تغییری از اون بخواه یک plan خلاصه بنویسه و مرحله‌به‌مرحله پیش بره، نه یک‌جا همه‌چیز را عوض کنه. این کد الان **روی سرور production در حال کار و دارای داده واقعیه**، پس بخش «مهاجرت داده‌های فعلی» را با دقت بخون.

---

## ۱. هدف

الان `repert-robot` یک اپ Laravel تک‌مستأجری (single-tenant) است: یک ربات بله، یک `bot_token`، و همه‌ی داده‌ها (نماینده‌ها، دپارتمان‌ها، دسته‌بندی‌ها، گزارش‌ها) مشترک و سراسری‌اند.

می‌خوایم به یک SaaS چندمستأجری تبدیلش کنیم:

1. هر کسی می‌تونه از طریق یک فرم عمومی ثبت‌نام کنه (بدون نیاز به دعوت).
2. بعد از ثبت‌نام، حساب در وضعیت «در انتظار تایید» می‌مونه و **نمی‌تونه** وارد پنل بشه.
3. فقط **سوپرادمین پلتفرم** (مالک واقعی این نصب Laravel — یعنی من) لیست درخواست‌ها رو می‌بینه و تایید/رد می‌کنه.
4. بعد از تایید، اون کاربر یک «سازمان/مستأجر» (tenant) مخصوص خودش داره: پنل ادمین خودش، دیتای خودش (نماینده، دپارتمان، دسته‌بندی، گزارش، تنظیمات)، **کاملاً ایزوله** از بقیه‌ی مستأجرها.
5. اون کاربر از پنل خودش توکن ربات بله‌ی خودش رو وارد می‌کنه، سیستم webhook مخصوص همون tenant رو روی بله ثبت می‌کنه، و از همون لحظه ربات خودش شروع به کار می‌کنه — کاملاً جدا از ربات‌های بقیه مستأجرها (چه‌بسا چند ربات بله‌ی متفاوت هم‌زمان روی همین یک اپ Laravel کار کنن).

---

## ۲. وضعیت فعلی که باید بشناسی (قبل از شروع، خودت هم در کد تایید کن)

- Laravel 11/13، SQLite در dev (میشه MySQL روی داکر production طبق `docker-compose.yml`).
- مسیر وبهوک فعلی ربات: `POST /api/bot/webhook` → `App\Http\Controllers\Api\BotController@handle` (تک‌مسیره، بدون شناسه tenant).
- توکن ربات و پیام‌های سیستم از جدول عمومی `settings` (`App\Models\Setting::get/set`) خونده می‌شه؛ `BotController::__construct()` توکن رو یک‌بار برای کل اپ می‌خونه: `Setting::get('bot_token')`.
- اتصال ربات: `App\Http\Controllers\Admin\SettingController@connect` با `Http::post("https://tapi.bale.ai/bot{$token}/setWebhook", ['url' => url('/api/bot/webhook')])`.
- جدول‌ها/مدل‌های داده‌ی کسب‌وکار (همه سراسری، بدون مالکیت مستأجر): `representatives` (`phone_number` و `chat_id` هرکدوم `unique` سراسری)، `provinces`، `departments`، `department_fields`، `categories`، `category_fields`، `field_options`، `reports`، `bot_states` (`chat_id` کلید مکالمه)، `monthly_statuses`، `broadcast_messages`، `settings`.
- کاربران پنل ادمین: `users` با `is_super_admin` (دسترسی کامل داخل همون یک سازمان) + `roles`/`role_user`/`role_department` برای دسترسی محدودتر. الان مفهوم «چند سازمان» اصلاً وجود نداره.
- لاگین فقط ایمیل/رمز است (`Auth/LoginController`)؛ فرم ثبت‌نام عمومی وجود نداره.
- Deploy: push به GitHub → روی سرور چابکان `bash deploy.sh` که شامل `composer install`، `npm build`، `php artisan migrate --force`، cache کردن است.

---

## ۳. طرح دیتابیس

### ۳.۱ جدول جدید `tenants`

```
tenants
  id
  name                 string   — نام سازمان/نماینده (از فرم ثبت‌نام)
  owner_name           string
  owner_phone          string nullable
  email                string   — همون ایمیل حساب کاربری مالک، برای تماس
  status               enum('pending','approved','rejected','suspended') default 'pending'
  bot_token            string nullable       — توکن ربات بله همین مستأجر
  bot_username         string nullable       — از getMe برمی‌گردد، برای نمایش در پنل
  webhook_secret       string unique nullable — رشته تصادفی غیرقابل حدس، برای مسیر webhook این tenant
  bot_connected_at     timestamp nullable
  approved_at          timestamp nullable
  approved_by          FK -> users.id nullable
  rejected_reason      text nullable
  suspended_at         timestamp nullable
  timestamps
```

`webhook_secret` را با `Str::random(48)` (یا uuid) بساز، **نه** از `id` عددی — چون در URL عمومی webhook قرار می‌گیرد و نباید قابل حدس زدن باشد.

### ۳.۲ اضافه کردن `tenant_id` به جدول‌های مالکیت‌دار مستأجر

روی همه‌ی این جدول‌ها ستون `tenant_id` (FK به `tenants.id`, `cascadeOnDelete` یا `restrictOnDelete` — تصمیم بگیر، `restrict` امن‌تره) اضافه کن:

- `users` (**nullable** — چون کاربر سوپرادمین پلتفرم به هیچ tenant تعلق ندارد)
- `roles`
- `representatives`
- `departments`
- `department_fields`
- `categories`
- `category_fields`
- `field_options`
- `reports`
- `bot_states`
- `monthly_statuses`
- `broadcast_messages`
- `settings`

جدول `provinces` را **سراسری/مشترک نگه دار** (لیست ثابت ۳۱ استان ایران، داده حساس یا اختصاصی نیست) — `tenant_id` بهش اضافه نکن، مگر این‌که بعداً معلوم بشه هر مستأجر باید بتونه لیست استان‌های خودش رو شخصی‌سازی کنه (این را به‌عنوان یک سوال باز در بخش ۹ هم مطرح کرده‌ام).

### ۳.۳ اصلاح Unique Constraint ها (نکته حیاتی برای درستی چندمستأجری بودن)

الان `representatives.phone_number` و `representatives.chat_id` به‌صورت **سراسری unique** هستند. اما در دنیای چند-رباتی، یک کاربر بله می‌تونه با چند ربات مختلف (متعلق به چند tenant مختلف) صحبت کنه و `chat_id`ش در هر دو جا به‌درستی یکسان باشه؛ همین‌طور یک شماره تلفن ممکنه در دو سازمان مختلف به‌عنوان دو نماینده جدا ثبت بشه. پس:

- `representatives`: یونیک `phone_number` سراسری رو بردار، به‌جاش `unique(tenant_id, phone_number)` بذار. همین کار رو برای `chat_id` هم بکن: `unique(tenant_id, chat_id)`.
- `bot_states`: مکالمه با `chat_id` پیدا می‌شه (`BotState::where('chat_id', $chatId)`)؛ باید ایندکس/لوکاپ به `(tenant_id, chat_id)` تبدیل بشه تا دو نماینده‌ی متفاوت با `chat_id` یکسان در دو ربات مختلف state جداگانه داشته باشن.
- `settings`: یونیک `key` سراسری رو بردار، `unique(tenant_id, key)` بذار.
- `roles.name` هم اگر یونیک سراسری بود، به `unique(tenant_id, name)` تبدیل کن.

---

## ۴. لایه Application: ایزوله‌سازی خودکار به‌جای دستی

هدف این است که هیچ کنترلری مجبور نباشه دستی `->where('tenant_id', ...)` بنویسه و یادش بره — باید ایزولاسیون **در سطح مدل** و به‌صورت خودکار انجام بشه، وگرنه یک `where` فراموش‌شده یعنی نشت داده بین مستأجرها.

### ۴.۱ `TenantContext`

یک سرویس ساده singleton بساز (مثلاً `app/Support/TenantContext.php`) که «مستأجر جاری درخواست» را نگه می‌دارد:

```php
class TenantContext {
    private static ?Tenant $tenant = null;
    public static function set(?Tenant $tenant): void { self::$tenant = $tenant; }
    public static function get(): ?Tenant { return self::$tenant; }
    public static function id(): ?int { return self::$tenant?->id; }
}
```

این باید در دو نقطه پر بشه:
1. **پنل ادمین**: در میدلور `AdminAuth` (یا یک میدلور جدید `ResolveTenant` بعد از آن)، از `Auth::user()->tenant` بخون و `TenantContext::set(...)` کن.
2. **وبهوک ربات**: از پارامتر مسیر (بخش ۶) resolve کن.

### ۴.۲ Trait برای مدل‌ها: `BelongsToTenant`

یک trait بساز (`app/Models/Concerns/BelongsToTenant.php`) که با `static::addGlobalScope` تمام کوئری‌های مدل رو به `tenant_id = TenantContext::id()` محدود کنه، و با `static::creating` مقدار `tenant_id` رو خودکار پر کنه اگر ست نشده باشه. این trait رو به همه‌ی مدل‌های لیست‌شده در ۳.۲ اضافه کن (به‌جز `User` — چون سوپرادمین پلتفرم `tenant_id = null` داره و باید بتونه بدون global scope کوئری بزنه؛ برای `User` بهتره اسکوپ رو دستی/شرطی بنویسی یا اصلاً اسکوپ نذاری و در کنترلرهای مدیریت کاربران دستی فیلتر کنی).

نکته: چون `BotController::handle` همه‌چیز رو داخل `DB::transaction` انجام می‌ده و از طریق مدل‌های Eloquent (نه `DB::table`) کار می‌کنه، این global scope به‌صورت شفاف روی `BotState::firstOrCreate`, `Representative::where(...)`, `Category::with(...)`, `Setting::get(...)` هم اعمال می‌شه — فقط باید مطمئن بشی `TenantContext::set()` **قبل از** رسیدن به این متدها انجام شده باشه.

### ۴.۳ ممیزی کامل کد برای query های خام

قبل از این‌که کار رو تمام‌شده بدونی، در کل پروژه (به‌خصوص `Admin/*Controller`، `Api/BotController`، `Services/BroadcastSender`، `Console/Commands/*`) دنبال این‌ها بگرد و مطمئن شو همه از طریق مدل‌های scoped عبور می‌کنن، نه raw query که از global scope فرار می‌کنه:
- `DB::table(...)`
- `::withoutGlobalScope`
- هر query builder ی که مستقیم از `DB::` استفاده می‌کنه

مخصوصاً `App\Console\Commands\SendDueBroadcasts` که با کرون اجرا می‌شه و **کاربر لاگین‌شده و tenant context از میدلور نداره** — این دستور باید روی هر tenant جدا-جدا لوپ بزنه و برای هر کدوم `TenantContext::set($tenant)` رو قبل از پردازش پیام‌های همون tenant صدا بزنه (وگرنه پیام‌های همگانی هیچ‌کدوم tenant مشخصی پیدا نمی‌کنن).

---

## ۵. ثبت‌نام عمومی و گردش تایید سوپرادمین

### ۵.۱ فرم ثبت‌نام (`GET/POST /register`)

`App\Http\Controllers\Auth\RegisterController` جدید:
- فیلدها: نام سازمان/نماینده، ایمیل، رمز عبور (+تکرار)، شماره تماس مالک.
- در یک تراکنش دیتابیس:
  - یک `Tenant` با `status = 'pending'` بساز.
  - یک `User` با `tenant_id` = همون tenant، `is_super_admin = true` (یعنی ادمین کامل **در محدوده‌ی سازمان خودش**)، `is_platform_admin = false` بساز.
- هیچ ورودی مربوط به توکن ربات این‌جا نباید باشه — اون فقط بعد از تایید و از پنل تنظیمات وارد می‌شه.
- بعد از ثبت‌نام کاربر رو **لاگین نکن**؛ یک پیام نشون بده: «درخواست شما ثبت شد؛ پس از تایید سوپرادمین امکان ورود خواهید داشت.»
- Rate limit روی این route بذار (`throttle`) تا جلوی ثبت‌نام انبوه/اسپم گرفته بشه.

### ۵.۲ گیت لاگین بر اساس وضعیت tenant

در `LoginController@login`، بعد از `Auth::attempt` موفق:
- اگر `$user->is_platform_admin` → به پنل سوپرادمین (بخش ۶) هدایتش کن، بدون بستن به هیچ tenant.
- وگرنه وضعیت `$user->tenant->status` رو چک کن:
  - `pending` → logout فوری + پیام «حساب شما هنوز توسط سوپرادمین تایید نشده است.»
  - `rejected` / `suspended` → logout + پیام مناسب (برای suspended می‌تونی دلیل رو هم نشون بدی).
  - `approved` → اجازه ورود، و `TenantContext::set($user->tenant)`.

### ۵.۳ ستون‌های جدید روی `users`

- `tenant_id` (FK nullable)
- `is_platform_admin` (boolean, default false)

`is_super_admin` فعلی همون‌جوری که هست بمونه، ولی معنیش می‌شه «ادمین کامل در سطح سازمان خودش»، نه سراسری.

---

## ۶. پنل سوپرادمین پلتفرم (جدا از پنل ادمین هر مستأجر)

یک ناحیه‌ی route کاملاً جدا و مجزا از `/admin` بساز، مثلاً `/platform` یا `/superadmin`:

```
Route::middleware(['auth', 'platform.admin'])->prefix('platform')->name('platform.')->group(function () {
    Route::get('/tenants',                    [Platform\TenantController::class, 'index'])->name('tenants.index');
    Route::get('/tenants/{tenant}',            [Platform\TenantController::class, 'show'])->name('tenants.show');
    Route::post('/tenants/{tenant}/approve',   [Platform\TenantController::class, 'approve'])->name('tenants.approve');
    Route::post('/tenants/{tenant}/reject',    [Platform\TenantController::class, 'reject'])->name('tenants.reject');
    Route::post('/tenants/{tenant}/suspend',   [Platform\TenantController::class, 'suspend'])->name('tenants.suspend');
    Route::post('/tenants/{tenant}/resume',    [Platform\TenantController::class, 'resume'])->name('tenants.resume');
});
```

میدلور `platform.admin`: فقط `is_platform_admin = true` رو رد می‌کنه (شبیه `AdminAuth` ولی این چک اضافه رو داره؛ و **نباید** `TenantContext` رو ست کنه — سوپرادمین پلتفرم به هیچ دیتای گزارشی مستأجرها دسترسی مستقیم نداره، فقط به متادیتای خودِ `tenants`).

صفحه‌ی `tenants.index`: سه تب/فیلتر (در انتظار تایید / تاییدشده / رد‌شده‌ یا معلق) با نام، ایمیل، تاریخ ثبت‌نام، و دکمه‌های تایید/رد.

`approve()`: `status = 'approved'`, `approved_at = now()`, `approved_by = auth()->id()`. (اختیاری: اطلاع‌رسانی ایمیلی به مالک — اگر بخوای بعداً اضافه می‌کنی، فعلاً لازم نیست.)

`suspend()`/`resume()`: برای این‌که سوپرادمین بتونه بعداً یک مستأجر متخلف رو موقتاً غیرفعال کنه بدون حذف داده‌هاش — وقتی `suspended`، هم لاگین کاربرانش قفل می‌شه (بخش ۵.۲) و هم وبهوک رباتش (بخش ۷) باید غیرفعال/بلاک بشه.

### حساب سوپرادمین اول

در `database/seeders/AdminSeeder.php` (که الان super admin تک اپ رو می‌سازه)، تبدیلش کن به ساخت یک `User` با `is_platform_admin = true` و `tenant_id = null` (بدون `is_super_admin` لازم نیست چون اصلاً هیچ tenant ای نداره). این می‌شه حساب من (سوپرادمین پلتفرم).

---

## ۷. اتصال ربات بله per-tenant (وبهوک چندگانه)

### ۷.۱ مسیر وبهوک

```php
Route::post('/api/bot/webhook/{tenant:webhook_secret}', [BotController::class, 'handle'])
    ->name('bot.webhook');
```

با route-model-binding روی ستون `webhook_secret` (نه `id`)، لاراول خودش `Tenant $tenant` رو پیدا می‌کنه یا 404 می‌ده اگر نبود.

### ۷.۲ بازنویسی `BotController`

- سازنده (`__construct`) که الان `Setting::get('bot_token')` رو یک‌بار برای کل اپ می‌خونه، از بین ببر (چون در زمان ساخت کنترلر هنوز پارامتر route در دسترس نیست).
- امضای `handle` را به `handle(Request $request, Tenant $tenant)` تغییر بده.
- ابتدای `handle`:
  - اگر `$tenant->status !== 'approved'` یا `$tenant->bot_token` خالیه یا `suspended` است → فقط `response('OK', 200)` برگردون (به بله چیزی نگو که باعث retry بشه، ولی هیچ پردازشی هم نکن) و لاگ بزن.
  - `TenantContext::set($tenant);`
  - `$this->token = $tenant->bot_token; $this->apiUrl = "https://tapi.bale.ai/bot{$this->token}/";` (به‌جای مقداردهی در constructor).
- بقیه‌ی متدهای private (`processMessage`, `handleStart`, ...) دست‌نخورده می‌مونن چون از طریق مدل‌های Eloquent با global scope کار می‌کنن؛ فقط مطمئن شو همه‌جا از `$this->token`/`$this->apiUrl` استفاده می‌کنن نه دوباره `Setting::get('bot_token')`.

### ۷.۳ بازنویسی `SettingController@connect` (و افزودن قطع اتصال)

- `bot_token` رو دیگه در جدول عمومی `settings` ذخیره نکن؛ مستقیم روی `TenantContext::get()->bot_token` (یعنی رکورد tenant کاربر لاگین‌شده) بنویس.
- اگر `webhook_secret` مستأجر خالیه، همین‌جا با `Str::random(48)` بسازش و ذخیره کن.
- آدرس وبهوک رو دیگه `url('/api/bot/webhook')` نساز؛ بساز: `route('bot.webhook', $tenant->webhook_secret)`.
- بعد از موفقیت `setWebhook`: `bot_connected_at = now()`، و می‌تونی با فراخوانی `getMe` نام کاربری ربات (`bot_username`) رو هم ذخیره و در پنل نشون بدی.
- یک اکشن جدید «قطع اتصال ربات» اضافه کن که `deleteWebhook` بله رو صدا بزنه و `bot_token`/`bot_connected_at` رو پاک کنه — برای وقتی مستأجر می‌خواد ربات دیگه‌ای وصل کنه یا موقتاً خاموشش کنه.
- پیام‌های welcome/error که در `settings` (حالا per-tenant) ذخیره می‌شن دست‌نخورده می‌مونن، فقط چون `Setting` هم trait ایزولاسیون رو داره، خودکار برای هر مستأجر جدا میشن.

### ۷.۴ وقتی سوپرادمین یک tenant را suspend می‌کند

باید (حداقل) از `deleteWebhook` روی بله استفاده کنی تا اون ربات دیگه پیام دریافت نکنه، یا حداقل در `BotController::handle` (بخش ۷.۲) چک وضعیت `suspended` رو رعایت کنی که هیچ پردازشی انجام نشه.

---

## ۸. مهاجرت داده‌های فعلی production (مهم — این اپ الان زنده است!)

الان دقیقاً یک سازمان با داده‌ی واقعی روی این اپ کار می‌کنه. migration ای که `tenant_id` رو اضافه می‌کنه **باید همزمان** یک tenant پیش‌فرض برای همین داده‌ی موجود بسازه، وگرنه بعد از deploy همه‌چیز می‌شکنه (رکوردهای موجود `tenant_id = null` می‌مونن و دیگه با global scope جدید پیدا نمی‌شن، یعنی از دید اپ انگار داده حذف شده).

مراحل پیشنهادی داخل یک migration جدا (بعد از migration هایی که ستون `tenant_id` رو اضافه می‌کنن، قبل از این‌که هر global scope فعال بشه):

1. یک رکورد در `tenants` بساز: `name` چیزی مثل «حوزه هنری (سازمان اصلی)»، `status = 'approved'`، `approved_at = now()`، `bot_token` = مقدار فعلی `Setting::get('bot_token')` (از جدول `settings` بخون قبل از این‌که خودِ `settings` هم `tenant_id`دار بشه)، `bot_username`/`webhook_secret` = تازه بساز.
2. با `DB::table(...)->update(['tenant_id' => $defaultTenantId])` روی همه‌ی جدول‌های لیست‌شده در ۳.۲ (`users`, `roles`, `representatives`, `departments`, `department_fields`, `categories`, `category_fields`, `field_options`, `reports`, `bot_states`, `monthly_statuses`, `broadcast_messages`, `settings`) این tenant پیش‌فرض رو به همه‌ی رکوردهای موجود نسبت بده.
3. کاربر(های) فعلی که `is_super_admin = true` دارن رو همون‌جوری با `tenant_id = default tenant` نگه دار (اونا ادمین همون سازمان می‌مونن). جدا از این، حساب من (که باید سوپرادمین پلتفرم بشم) رو یا با یک artisan command یکبار‌مصرف (`php artisan tenants:promote-platform-admin {email}`) به `is_platform_admin = true, tenant_id = null` تبدیل کن، یا اگر می‌خوام همون ایمیل فعلی‌ام هم ادمین سازمان اصلی بمونه و هم سوپرادمین پلتفرم باشه، دو حساب جدا (یکی platform admin بدون tenant، یکی tenant admin) بساز — این دومی رو به‌عنوان یک سوال باز به من مطرح کن، خودت به‌صورت یک‌طرفه تصمیم نگیر.
4. بعد از این‌که `webhook_secret` مستأجر پیش‌فرض ساخته شد، باید وبهوک بله (روی خود بله، از طریق `setWebhook`) رو به آدرس جدید `/api/bot/webhook/{webhook_secret}` **آپدیت** کنی (یا یک artisan command برای این کار بنویس که بعد از deploy دستی اجرا بشه)، وگرنه ربات فعلی که مشتری‌های واقعی دارن باهاش کار می‌کنن قطع می‌شه.

قبل از اجرای این migration روی سرور، حتماً:
- از دیتابیس production بک‌آپ کامل بگیر.
- اول روی یک کپی/staging امتحانش کن.
- بعد از `php artisan migrate --force` بلافاصله چک کن که ربات فعلی هنوز جواب می‌ده (یک پیام تستی به ربات بفرست) و پنل ادمین فعلی هنوز لاگین و دیتا رو نشون می‌ده.

---

## ۹. نکات امنیتی و چک‌لیست ایزولاسیون

- `webhook_secret` باید طولانی و رندوم باشه (حداقل ۳۲ کاراکتر)، نه ترتیبی/قابل حدس.
- در همه‌ی route های `/admin/*` که با `{model}` پارامتر می‌گیرن (`representatives/{representative}`, `reports/{report}`, `categories/{category}`, ...)، چون global scope مدل‌ها فعاله، اگر یک کاربر tenant A بخواد با تغییر عدد در URL به رکورد tenant B دسترسی پیدا کنه (IDOR)، باید ۴۰۴ بگیره نه دیتای مستأجر دیگه — این رو حتماً به‌صورت دستی تست کن (بخش ۱۰).
- فایل‌های آپلودی (عکس/گزارش) در `storage` ذخیره می‌شن؛ اگر مسیر ذخیره‌سازی بر اساس `report_id`/`representative_id` است و این آی‌دی‌ها فقط بین tenant های مختلف یکتا نیستن (که نیستن، چون auto-increment سراسریه، پس یکتا هستن) مشکلی نیست؛ فقط مطمئن شو مسیر دانلود/نمایش فایل هم از کنترلر scoped عبور می‌کنه نه از یک URL عمومی حدس‌زدنی.
- Rate limit روی `/register` و روی خود `/api/bot/webhook/{secret}` (در صورت امکان) بذار.
- لاگ‌ها (`Log::info` در `BotController`) بهتره `tenant_id` رو هم داخلشون بنویسن تا دیباگ چندمستأجری راحت‌تر بشه.

---

## ۱۰. چک‌لیست تست نهایی (باید همه‌ی این‌ها را دستی یا با تست خودکار تایید کنی)

1. ثبت‌نام یک حساب جدید از `/register` → تلاش برای لاگین → باید پیام «در انتظار تایید» بگیره، وارد پنل نشه.
2. با حساب سوپرادمین پلتفرم وارد `/platform/tenants` بشو، همون درخواست رو تایید کن.
3. حالا با همون حساب جدید لاگین کن → باید پنل ادمین **خالی** (بدون دپارتمان/دسته‌بندی/نماینده/گزارشِ مستأجر دیگر) رو ببینی.
4. از تنظیمات، یک توکن ربات بله‌ی دوم (متفاوت از ربات اصلی) وصل کن → `bot_connected_at` ست بشه، وبهوک روی بله با URL مخصوص همین tenant ثبت بشه.
5. از یک اکانت بله‌ی واقعی به ربات دوم پیام بده، `/start` بزن، شماره بفرست، یک گزارش کامل ثبت کن → مطمئن شو در پنل همین tenant جدید دیده می‌شه.
6. برو داخل پنل مستأجر اصلی (قدیمی) و مطمئن شو **هیچ اثری** از این گزارش/نماینده‌ی جدید اونجا نیست، و برعکس.
7. تست IDOR: با حساب tenant جدید لاگین باش، شماره‌ی `id` یک گزارش/نماینده از tenant اصلی رو حدس بزن و مستقیم در URL پنل امتحان کن (مثلاً `/admin/reports/1`) → باید ۴۰۴/۴۰۳ بگیری.
8. یک tenant رو `suspend` کن → لاگین کاربرانش قفل بشه، وبهوک رباتش دیگه پردازش نکنه.
9. `SendDueBroadcasts` (کرون پیام همگانی) رو دستی اجرا کن با حداقل دو tenant فعال که هرکدوم broadcast زمان‌بندی‌شده دارن → مطمئن شو پیام هرکدوم فقط برای نماینده‌های همون tenant می‌ره.
10. سناریوی مهاجرت (بخش ۸) روی یک کپی از دیتابیس production را کامل تست کن: بعد از migrate، ربات و پنل قدیمی هنوز درست کار می‌کنن.

---

## ۱۱. سوالات بازی که باید قبل/حین اجرا از من (صاحب پروژه) بپرسی، نه خودسرانه تصمیم بگیری

- آیا `provinces` باید سراسری/مشترک بمونه (پیشنهاد پیش‌فرض بالا) یا هر مستأجر لیست استان‌های مستقل خودش رو داشته باشه؟
- برای حساب فعلی من: می‌خوام هم سوپرادمین پلتفرم باشم و هم همچنان ادمین سازمان اصلی (دو نقش با یک ایمیل/دو حساب)، یا فقط سوپرادمین پلتفرم بشم و مدیریت سازمان اصلی رو به یک حساب جدا بسپارم؟
- آیا مستأجر تازه‌تاییدشده باید یک نمونه‌ی خالی کامل شروع کنه (پیشنهاد پیش‌فرض)، یا یک قالب پیش‌فرض از دپارتمان/دسته‌بندی (کپی از یک نمونه) براش کپی بشه تا از صفر شروع نکنه؟
- آیا لازم است بعد از تایید سوپرادمین، ایمیل/پیامکی به مستأجر اطلاع بده؟ (فعلاً زیرساخت ایمیل در پروژه دیده نشد.)
- سقف تعداد کاربر ادمین/نماینده در هر مستأجر، یا محدودیت پلن (رایگان/پولی) لازم است یا فعلاً نه؟

---

## ۱۲. خارج از محدوده‌ی این تغییر (فعلاً لازم نیست انجام بشه)

- ساب‌دامین یا دامنه‌ی اختصاصی برای هر مستأجر — همه از یک دامنه با پنل جدا بر اساس حساب کاربری استفاده می‌کنن.
- صورتحساب/پرداخت (billing) — این فقط تایید دستی سوپرادمینه، نه پلن پولی.
- Impersonation (سوپرادمین وارد پنل یک مستأجر بشه برای پشتیبانی) — می‌تونه فاز بعدی باشه.

---

**خلاصه‌ی سفارش برای عامل کدنویس:** اول یک plan کوتاه (لیست migration ها، مدل‌ها، کنترلرها، route ها) بر اساس این سند بنویس و نشونم بده؛ بعد از تایید من، پیاده‌سازی رو مرحله‌به‌مرحله (اول دیتابیس+مدل‌ها، بعد ثبت‌نام/تایید، بعد پنل سوپرادمین، بعد وبهوک چندگانه، در آخر مهاجرت داده‌ی production) انجام بده و بعد از هر مرحله بگو با چه دستوری (`php artisan migrate`, تست‌های artisan/pest و ...) تاییدش کنم. migrate کردن دیتابیس production رو خودت اجرا نکن — فقط دستورش رو بده تا من روی سرور چابکان اجرا کنم.
