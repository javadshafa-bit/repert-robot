<?php

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
use App\Http\Controllers\Api\BotController;
use Illuminate\Support\Facades\Route;

Route::post('/api/bot/webhook', [BotController::class, 'handle']);

// Auth
Route::get('/login',  [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Admin panel
Route::middleware('admin.auth')->prefix('admin')->name('admin.')->group(function () {

    // داشبورد - بدون نیاز به دسترسی خاص
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // تنظیمات
    Route::middleware('admin.can:settings')->group(function () {
        Route::get('/settings',          [SettingController::class, 'index'])->name('settings.index');
        Route::post('/settings',         [SettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/connect', [SettingController::class, 'connect'])->name('settings.connect');
        Route::post('/settings/flow',    [SettingController::class, 'updateFlow'])->name('settings.flow');
    });

    // نمایندگان
    Route::middleware('admin.can:representatives')->group(function () {
        Route::resource('representatives', RepresentativeController::class);
    });

    // دسته‌بندی و فرم‌ساز
    Route::middleware('admin.can:categories')->group(function () {
        Route::resource('categories', CategoryController::class);
        Route::get('categories/{category}/tree-fragment',                                 [CategoryController::class, 'treeFragment'])->name('categories.tree-fragment');
        Route::post('categories/{category}/fields',                                        [CategoryController::class, 'storeField'])->name('categories.fields.store');
        Route::post('categories/{category}/fields/reorder',                                [CategoryController::class, 'reorderFields'])->name('categories.fields.reorder');
        Route::put('categories/{category}/fields/{field}',                                 [CategoryController::class, 'updateField'])->name('categories.fields.update');
        Route::delete('categories/{category}/fields/{field}',                              [CategoryController::class, 'destroyField'])->name('categories.fields.destroy');
        // مدیریت گزینه‌های option fields
        Route::post('categories/{category}/fields/{field}/options',                        [CategoryController::class, 'storeOption'])->name('categories.fields.options.store');
        Route::put('categories/{category}/fields/{field}/options/{option}',               [CategoryController::class, 'updateOption'])->name('categories.fields.options.update');
        Route::delete('categories/{category}/fields/{field}/options/{option}',             [CategoryController::class, 'destroyOption'])->name('categories.fields.options.destroy');
    });

    // دپارتمان‌ها
    Route::middleware('admin.can:departments')->group(function () {
        Route::resource('departments', DepartmentController::class);
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
