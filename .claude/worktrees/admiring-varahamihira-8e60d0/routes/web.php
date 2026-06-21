<?php

use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RepresentativeController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\ExportController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\BotController;

Route::post('/api/bot/webhook', [BotController::class, 'handle']);
// Auth
Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Admin panel (protected)
Route::middleware('admin.auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Settings
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('/settings/connect', [SettingController::class, 'connect'])->name('settings.connect');

    // Representatives
    Route::resource('representatives', RepresentativeController::class);

    // Categories + Fields
    Route::resource('categories', CategoryController::class);
    Route::post('categories/{category}/fields', [CategoryController::class, 'storeField'])->name('categories.fields.store');
    Route::put('categories/{category}/fields/{field}', [CategoryController::class, 'updateField'])->name('categories.fields.update');
    Route::delete('categories/{category}/fields/{field}', [CategoryController::class, 'destroyField'])->name('categories.fields.destroy');

    // Departments
    Route::resource('departments', DepartmentController::class);

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');

    // Export
    Route::get('/export/reports', [ExportController::class, 'reports'])->name('export.reports');
});

Route::get('/', fn() => redirect()->route('login'));