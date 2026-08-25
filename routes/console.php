<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ارسال پیام‌های همگانی زمان‌بندی‌شده — نیازمند cron: * * * * * php artisan schedule:run
Schedule::command('broadcast:send-due')->everyMinute();

// انقضای اشتراک‌ها — هر شب ۰۰:۱۰ به وقت تهران.
// اگر بین دو اجرا اشتراکی تمام شود، میدلور ResolveTenant موقع ورود کاربر
// همان لحظه وضعیت را هم‌گام می‌کند و ربات هم مستقل از این ستون چک می‌شود.
Schedule::command('subscriptions:expire')->dailyAt('00:10')->timezone('Asia/Tehran');
