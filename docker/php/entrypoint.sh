#!/bin/bash
# entrypoint کانتینر app — قبل از بالا آمدن php-fpm اجرا می‌شود
set -e

cd /var/www/html

# مراحل آماده‌سازی فقط وقتی سرویس اصلی بالا می‌آید اجرا شوند.
# برای دستورات یک‌بارمصرف (artisan، php -v، bash) مستقیم اجرا شود.
if [ "${1:-}" != "php-fpm" ]; then
  exec "$@"
fi

echo "⏳ انتظار برای آماده شدن دیتابیس..."
for i in $(seq 1 60); do
  if php -r '
    try {
      new PDO(
        sprintf("mysql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT") ?: 3306, getenv("DB_DATABASE")),
        getenv("DB_USERNAME"), getenv("DB_PASSWORD")
      );
      exit(0);
    } catch (Throwable $e) { exit(1); }
  ' 2>/dev/null; then
    echo "✅ دیتابیس آماده است"
    break
  fi
  [ "$i" = "60" ] && { echo "❌ دیتابیس در دسترس نیست"; exit 1; }
  sleep 2
done

# اگر APP_KEY خالی بود بساز (فقط بار اول)
if [ -z "${APP_KEY:-}" ]; then
  echo "⚠️  APP_KEY تنظیم نشده — یکی ساخته می‌شود (حتماً در .env ذخیره‌اش کنید)"
  php artisan key:generate --show
fi

echo "🗃️  اجرای migration ها..."
php artisan migrate --force

echo "🔗 storage:link..."
php artisan storage:link 2>/dev/null || true

echo "⚡ کش کردن برای production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data storage bootstrap/cache

echo "🚀 اجرای: $*"
exec "$@"
