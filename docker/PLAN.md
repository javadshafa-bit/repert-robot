# پلن مهاجرت repert-robot به سرور Simorgh

## وضعیت فعلی
- سرور: Ubuntu 26.04، ۸ vCPU، ۳۰GB RAM، ۱۹۰GB دیسک خالی، Docker 29.7.1 + Compose v5.3.1
- روی سرور: استک `simurgh-prod` (Django + Celery + Postgres + Redis + Elasticsearch + MinIO)
- کانتینر `simurgh-prod-nginx` مستقیماً پورت ۸۰ و ۴۴۳ هاست را گرفته
- دامنه فعلی simurgh: `pakhshemoon.ir`
- دامنه جدید repert-robot: `a1-studio.ir`

## تصمیمات گرفته‌شده
| موضوع | تصمیم |
|---|---|
| Reverse proxy | Traefik v3 با Let's Encrypt خودکار |
| شبکه مشترک | شبکه‌ی داکر خارجی به نام `web` |
| دیتابیس repert-robot | MySQL 8 در کانتینر جدا با volume (مطابق CLAUDE.md، پایدارتر از SQLite برای production) |
| داده‌های چابکان | همه منتقل می‌شوند (دیتابیس + storage) |
| ری‌استارت nginx سایت simurgh | فقط در ساعت هماهنگ‌شده با کاربر |

## معماری هدف

```
اینترنت (۸۰/۴۴۳)
      │
   Traefik  ── شبکه web ──┬── simurgh-prod-nginx  → pakhshemoon.ir
                          └── repert-nginx        → a1-studio.ir
                                    │ (شبکه داخلی repert)
                                    ├── repert-app (PHP-FPM)
                                    └── repert-mysql (volume)
```

## گام‌ها

### گام ۱ — بررسی وضعیت (بدون تغییر) ← اینجاییم
اجرای `step1-inspect.sh` روی سرور برای دیدن compose فعلی simurgh، کانفیگ nginx، محل گواهی‌های SSL و رزولوشن DNS.

### گام ۲ — ساخت استک Traefik
- شبکه `web` ساخته می‌شود
- Traefik بالا می‌آید ولی **روی پورت‌های موقت** (مثلاً ۸۰۸۰/۸۴۴۳) تا با nginx فعلی تداخل نکند
- تست می‌شود که سالم بالا می‌آید

### گام ۳ — داکرایز کردن repert-robot
- `Dockerfile` چندمرحله‌ای: composer install + npm build + PHP 8.3-FPM
- `docker-compose.yml`: app + nginx + mysql + volumeها
- لیبل‌های Traefik برای `a1-studio.ir`
- بالا آوردن و تست داخلی (بدون DNS)

### گام ۴ — انتقال داده از چابکان
- دامپ گرفتن از دیتابیس فعلی چابکان
- تبدیل به MySQL در صورت نیاز
- restore روی کانتینر MySQL جدید
- کپی `storage/app` (فایل‌ها و عکس‌های آپلودشده)

### گام ۵ — مهاجرت nginx سایت simurgh (⚠️ ساعت هماهنگ‌شده)
- حذف بایند `80:80` و `443:443` از compose سایت simurgh
- اتصال آن کانتینر به شبکه `web` + اضافه کردن لیبل `pakhshemoon.ir`
- انتقال Traefik به پورت‌های اصلی ۸۰/۴۴۳
- **قطعی حدود ۱-۲ دقیقه روی سایت simurgh**
- پلن بازگشت: برگرداندن پورت‌ها به compose قبلی و `docker compose up -d`

### گام ۶ — DNS، SSL و webhook ربات
- اشاره دادن رکورد A دامنه `a1-studio.ir` به IP سرور
- گرفتن گواهی Let's Encrypt (خودکار توسط Traefik)
- ست کردن webhook بله روی `https://a1-studio.ir/api/bot/webhook`

### گام ۷ — تست نهایی
- باز شدن هر دو دامنه با HTTPS
- تست end-to-end ثبت گزارش از ربات
- بررسی لاگ‌ها و صحت داده‌های منتقل‌شده
- تنظیم بکاپ خودکار دیتابیس

## نکات ریسک
- تنها نقطه‌ی پرریسک، گام ۵ است (دست زدن به سرویس در حال کار simurgh). قبل از آن حتماً از compose فعلی simurgh بکاپ گرفته می‌شود.
- تا قبل از گام ۵، هیچ چیزی روی سایت فعلی تغییر نمی‌کند.
