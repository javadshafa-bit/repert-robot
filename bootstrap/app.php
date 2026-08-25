<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // پشت reverse proxy (Traefik → nginx) اجرا می‌شود؛ بدون این،
        // Laravel آدرس‌ها را http می‌سازد و IP واقعی کاربر را نمی‌بیند.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'admin.auth' => \App\Http\Middleware\AdminAuth::class,
            'admin.can'  => \App\Http\Middleware\CheckPermission::class,
            'tenant'     => \App\Http\Middleware\ResolveTenant::class,
            'platform.admin' => \App\Http\Middleware\PlatformAdmin::class,
        ]);
        // غیرفعال کردن CSRF برای Webhook بله
        // هر دو شکل مسیر باید مستثنا باشد: مسیر per-tenant و مسیر سازگاری قدیمی.
        // الگوی «api/bot/webhook/*» به‌تنهایی مسیر بدون اسلشِ انتهایی را نمی‌گیرد و آن مسیر ۴۱۹ می‌شود.
        $middleware->preventRequestForgery(except: [
            'api/bot/webhook',
            'api/bot/webhook/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
