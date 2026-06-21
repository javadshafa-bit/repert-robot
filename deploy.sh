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

# ۶. کش کردن برای production
echo "⚡ caching for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ۷. پاک کردن bot_states های نیمه‌کاره (چون فرمت draft_data عوض شده)
echo "🤖 clearing in-progress bot states..."
php artisan tinker --execute="
\App\Models\BotState::where('step', '!=', 'idle')->update([
    'step'        => 'idle',
    'draft_data'  => null,
    'field_queue' => null,
]);
echo 'Bot states cleared.';
"

# ۸. symlink storage
php artisan storage:link --force 2>/dev/null || true

echo ""
echo "✅ دیپلوی با موفقیت انجام شد!"
