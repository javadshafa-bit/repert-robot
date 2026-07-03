<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ارسال پیام‌های همگانی زمان‌بندی‌شده — نیازمند cron: * * * * * php artisan schedule:run
Schedule::command('broadcast:send-due')->everyMinute();
