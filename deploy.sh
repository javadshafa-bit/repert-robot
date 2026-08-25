#!/bin/bash
# ============================================
# Deploy Script — چابکان
# ============================================
set -e  # در صورت هر خطا متوقف شو

echo "🚀 شروع دیپلوی..."

# ۱. دریافت آخرین کد از GitHub
echo "📥 git pull..."
git pull origin main

# ۲. نصب وابستگی‌های PHP (بدون dev packages)
echo "📦 composer install..."
composer install --no-dev --optimize-autoloader --no-interaction

# ۳. ساخت assets
echo "🎨 npm build..."
npm ci --prefer-offline
npm run build

# ۴. پاک کردن cache های قدیمی
echo "🧹 clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# ۵. اجرای migration ها (شامل تبدیل داده قدیمی)
echo "🗃️  running migrations..."
php artisan migrate --force

# ─── هشدار: مسیر وبهوک عوض شده است ─────────────────────────────────────────
echo ""
echo "\033[43;30m ⚠️  توجه \033[0m"
echo "\033[33mمسیر وبهوک هر سازمان حالا /api/bot/webhook/{secret} است.\033[0m"
echo "\033[33mتا وقتی این دستور را اجرا نکنی، ربات‌ها روی مسیر قدیمیِ سازگاری کار می‌کنند\033[0m"
echo "\033[33m(یعنی همه‌ی پیام‌ها به سازمانِ پیش‌فرض می‌رود، نه سازمان خودشان):\033[0m"
echo ""
echo "    php artisan tenants:refresh-webhook"
echo ""
echo "\033[33mبعد از اجرای موفق آن، بلوک bot.webhook.legacy را از routes/web.php حذف کن.\033[0m"
echo ""

# ۶. کش کردن برای production
echo "⚡ caching for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ۷. (حذف شد) قبلاً اینجا همه‌ی bot_states نیمه‌کاره پاک می‌شد — روی هر دیپلوی، بدون
# شرط، حتی وقتی فرمت داده تغییری نکرده بود. این باعث می‌شد کاربرانی که وسط پرکردن
# گزارش بودن، گزارششون به‌طور کامل از دست بره (بدون هیچ خطایی به خودشون).
# اگر واقعاً فرمت draft_data/field_queue تغییر کرد، این دستور رو دستی و آگاهانه
# (و بعد از اطلاع‌رسانی به نمایندگان) اجرا کن، نه به‌صورت خودکار در هر push:
#
#   php artisan tinker --execute="
#   \App\Models\BotState::where('step', '!=', 'idle')->update([
#       'step' => 'idle', 'draft_data' => null, 'field_queue' => null,
#   ]);
#   "

# ۸. symlink storage
php artisan storage:link --force 2>/dev/null || true

# ۹. (فقط یک‌بار، بعد از دیپلویِ چندمستأجری) ثبت دوباره‌ی وبهوک بله
# مسیر وبهوک از /api/bot/webhook به /api/bot/webhook/{secret} تغییر کرده است؛
# تا این دستور اجرا نشود، ربات‌های فعلی پیامی دریافت نمی‌کنند.
# عمداً خودکار اجرا نمی‌شود چون به API بله درخواست می‌زند:
#
#   php artisan tenants:refresh-webhook
#
# و برای ساخت حساب سوپرادمین پلتفرم (یک‌بار):
#
#   php artisan tenants:create-platform-admin owner@example.com --name="سوپرادمین"

echo ""
echo "✅ دیپلوی با موفقیت انجام شد!"
