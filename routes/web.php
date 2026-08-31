<?php

use App\Http\Controllers\Admin\BotTextController;
use App\Http\Controllers\Admin\BroadcastController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\RepresentativeController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\Platform\DashboardController as PlatformDashboardController;
use App\Http\Controllers\Platform\DiscountCodeController;
use App\Http\Controllers\Platform\PlatformSettingController;
use App\Http\Controllers\Platform\TenantController;
use App\Http\Controllers\Platform\TenantMonitorController;
use App\Http\Controllers\Api\BotController;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// وبهوک ربات: هر مستأجر مسیر اختصاصی و غیرقابل حدس خودش را دارد
// throttle نام‌دار است تا سقف درخواست per-tenant باشد نه per-IP (بله از چند IP محدود می‌زند).
Route::post('/api/bot/webhook/{tenant:webhook_secret}', [BotController::class, 'handle'])
    ->middleware('throttle:bot-webhook')
    ->name('bot.webhook');

// ─── مسیر سازگاری موقت ───────────────────────────────────────────────────────
// ربات‌هایی که پیش از چندمستأجری‌سازی روی این آدرس setWebhook شده‌اند تا لحظه‌ی اجرای
// `php artisan tenants:refresh-webhook` روی سرور، همچنان به همین‌جا POST می‌کنند.
// بدون این مسیر، از پایان deploy تا اجرای آن دستور همه‌ی پیام‌های نمایندگان ۴۰۴ می‌گیرد.
// بعد از اجرای موفق آن دستور (و اطمینان از برگشتن ربات‌ها) این بلوک حذف شود.
Route::post('/api/bot/webhook', function (Request $request, BotController $controller) {
    $tenant = Tenant::legacyWebhookOwner();

    abort_if($tenant === null, 404);

    return $controller->handle($request, $tenant);
})->middleware('throttle:bot-webhook')->name('bot.webhook.legacy');

// Auth
Route::get('/login',  [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ثبت‌نام عمومی سازمان (نتیجه: مستأجر در وضعیت «در انتظار پرداخت» + ورود خودکار)
Route::middleware('throttle:10,1')->group(function () {
    Route::get('/register',  [RegisterController::class, 'showRegister'])->name('register');
    Route::post('/register', [RegisterController::class, 'register'])->name('register.post');
});

// صورتحساب و اشتراک سازمان — تنها ناحیه‌ای که سازمانِ پرداخت‌نکرده/منقضی می‌بیند.
// عمداً داخل گروه admin نیست، ولی همان میدلور tenant را دارد تا TenantContext ست شود.
Route::middleware(['admin.auth', 'tenant'])->prefix('billing')->name('billing.')->group(function () {
    Route::get('/',          [BillingController::class, 'index'])->name('index');
    Route::post('/quote',    [BillingController::class, 'quote'])->name('quote');
    Route::post('/pay',      [BillingController::class, 'pay'])->middleware('throttle:20,1')->name('pay');
    // بازگشت از درگاه با GET است (پس CSRF موضوعیت ندارد) ولی throttle لازم دارد
    Route::get('/callback',  [BillingController::class, 'callback'])->middleware('throttle:30,1')->name('callback');
    Route::get('/invoices',  [BillingController::class, 'invoices'])->name('invoices');
    Route::get('/receipt/{payment}', [BillingController::class, 'receipt'])->name('receipt');
});

// پنل سوپرادمین پلتفرم (مالک این نصب) — کاملاً جدا از پنل مستأجرها.
// همه‌ی صفحات نظارتی فقط‌خواندنی‌اند؛ سوپرادمین حق اتصال ربات یا ویرایش داده ندارد.
Route::middleware('platform.admin')->prefix('platform')->name('platform.')->group(function () {
    Route::get('/',                            [PlatformDashboardController::class, 'index'])->name('dashboard');
    Route::get('/tenants',                     [TenantController::class, 'index'])->name('tenants.index');
    Route::get('/tenants/{tenant}',            [TenantController::class, 'show'])->name('tenants.show');
    Route::post('/tenants/{tenant}/suspend',   [TenantController::class, 'suspend'])->name('tenants.suspend');
    Route::post('/tenants/{tenant}/resume',    [TenantController::class, 'resume'])->name('tenants.resume');

    // تعیین دستی دوره‌ی اشتراک (هر تغییر در subscription_logs ثبت می‌شود)
    Route::post('/tenants/{tenant}/subscription', [TenantController::class, 'subscription'])->name('tenants.subscription');

    // صفحات نظارتی یک سازمان — همه فقط‌خواندنی
    Route::controller(TenantMonitorController::class)->prefix('tenants/{tenant}')->name('tenants.')->group(function () {
        Route::get('/representatives',     'representatives')->name('representatives');
        Route::get('/departments',         'departments')->name('departments');
        Route::get('/categories',          'categories')->name('categories');
        Route::get('/reports',             'reports')->name('reports');
        Route::get('/reports/{report}',    'report')->whereNumber('report')->name('report');
        Route::get('/broadcasts',          'broadcasts')->name('broadcasts');
        Route::get('/payments',            'payments')->name('payments');
        Route::get('/subscription-logs',   'subscriptionLogs')->name('subscription-logs');
    });

    // کدهای تخفیف (سراسری، مال پلتفرم)
    Route::resource('discount-codes', DiscountCodeController::class)->except('show');
    Route::patch('discount-codes/{discount_code}/toggle', [DiscountCodeController::class, 'toggle'])
        ->name('discount-codes.toggle');

    // تنظیمات پلتفرم (قیمت روزانه، درگاه)
    Route::get('/settings',  [PlatformSettingController::class, 'edit'])->name('settings.edit');
    Route::post('/settings', [PlatformSettingController::class, 'update'])->name('settings.update');
});

// Admin panel
Route::middleware(['admin.auth', 'tenant'])->prefix('admin')->name('admin.')->group(function () {

    // داشبورد - بدون نیاز به دسترسی خاص
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // تنظیمات
    Route::middleware('admin.can:settings')->group(function () {
        Route::get('/settings',          [SettingController::class, 'index'])->name('settings.index');
        Route::post('/settings',         [SettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/connect',    [SettingController::class, 'connect'])->name('settings.connect');
        Route::post('/settings/disconnect', [SettingController::class, 'disconnect'])->name('settings.disconnect');
        Route::post('/settings/flow',    [SettingController::class, 'updateFlow'])->name('settings.flow');

        // متن‌های ربات
        Route::get('/bot-texts',        [BotTextController::class, 'index'])->name('bot-texts.index');
        Route::post('/bot-texts',       [BotTextController::class, 'update'])->name('bot-texts.update');
        Route::post('/bot-texts/reset', [BotTextController::class, 'reset'])->name('bot-texts.reset');
    });

    // نمایندگان
    Route::middleware('admin.can:representatives')->group(function () {
        Route::resource('representatives', RepresentativeController::class);
    });

    // دسته‌بندی و فرم‌ساز
    Route::middleware('admin.can:categories')->group(function () {
        Route::resource('categories', CategoryController::class);
        Route::patch('categories/{category}/toggle-active', [CategoryController::class, 'toggleActive'])->name('categories.toggle-active');
        Route::get('categories/{category}/tree-fragment',                                 [CategoryController::class, 'treeFragment'])->name('categories.tree-fragment');
        Route::post('categories/{category}/fields',                                        [CategoryController::class, 'storeField'])->name('categories.fields.store');
        Route::put('categories/{category}/fields/{field}',                                 [CategoryController::class, 'updateField'])->name('categories.fields.update');
        Route::delete('categories/{category}/fields/{field}',                              [CategoryController::class, 'destroyField'])->name('categories.fields.destroy');
        // مدیریت گزینه‌های option fields
        Route::post('categories/{category}/fields/{field}/options',                        [CategoryController::class, 'storeOption'])->name('categories.fields.options.store');
        Route::put('categories/{category}/fields/{field}/options/{option}',               [CategoryController::class, 'updateOption'])->name('categories.fields.options.update');
        Route::delete('categories/{category}/fields/{field}/options/{option}',             [CategoryController::class, 'destroyOption'])->name('categories.fields.options.destroy');
        // جابجایی (reparent) و کپی گروهی
        Route::patch('categories/{category}/fields/{field}/reparent',                      [CategoryController::class, 'reparentField'])->name('categories.fields.reparent');
        Route::patch('categories/{category}/fields/{field}/options/{option}/reparent',     [CategoryController::class, 'reparentOption'])->name('categories.fields.options.reparent');
        Route::post('categories/{category}/fields/{fieldTarget}/options/batch-copy',       [CategoryController::class, 'batchCopyOptions'])->name('categories.fields.options.batch-copy');
        Route::post('categories/{category}/fields/{field}/duplicate',                      [CategoryController::class, 'duplicateField'])->name('categories.fields.duplicate');
        Route::post('categories/{category}/fields/{field}/move',                           [CategoryController::class, 'moveField'])->name('categories.fields.move');
        Route::post('categories/{category}/fields/{field}/insert-in-chain',                [CategoryController::class, 'insertFieldInChain'])->name('categories.fields.insert-in-chain');
    });

    // دپارتمان‌ها
    Route::middleware('admin.can:departments')->group(function () {
        Route::resource('departments', DepartmentController::class);
    });

    // پیام همگانی
    Route::middleware('admin.can:broadcasts')->group(function () {
        Route::get('/broadcasts',                      [BroadcastController::class, 'index'])->name('broadcasts.index');
        Route::get('/broadcasts/create',               [BroadcastController::class, 'create'])->name('broadcasts.create');
        Route::post('/broadcasts',                     [BroadcastController::class, 'store'])->name('broadcasts.store');
        Route::patch('/broadcasts/{broadcast}/toggle', [BroadcastController::class, 'toggle'])->name('broadcasts.toggle');
        Route::delete('/broadcasts/{broadcast}',       [BroadcastController::class, 'destroy'])->name('broadcasts.destroy');
    });

    // گزارش‌ها
    Route::middleware('admin.can:reports')->group(function () {
        Route::get('/reports',          [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
        Route::delete('/reports/{report}', [ReportController::class, 'destroy'])->name('reports.destroy');
    });

    // خروجی اکسل
    Route::middleware('admin.can:reports.export')->group(function () {
        Route::get('/export/reports', [ExportController::class, 'reports'])->name('export.reports');
    });

    // مدیریت کاربران و نقش‌ها
    Route::middleware('admin.can:users')->group(function () {
        Route::resource('users', UserController::class);
        Route::resource('roles', RoleController::class);
    });
});

Route::get('/', fn() => redirect()->route('login'));
