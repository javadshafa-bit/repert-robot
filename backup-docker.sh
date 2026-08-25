#!/usr/bin/env bash
# ============================================================
# backup-docker.sh — بکاپ کامل استک داکری، قبل از deploy
#
#   bash backup-docker.sh
#
# روی *هاست* اجرا می‌شود (نه داخل کانتینر) و خروجی را در ./backups می‌گذارد،
# جایی که با rebuild ایمیج از بین نمی‌رود:
#
#   db-<زمان>.sql.gz        دامپ کامل MySQL
#   files-<زمان>.tar.gz     فایل‌های آپلودی (volume مربوط به storage/app)
#   rowcounts-<زمان>.txt    تعداد رکورد هر جدول، برای مقایسه بعد از migrate
#   env-<زمان>.bak          کپی .env.docker  ⚠️ شامل APP_KEY و رمز دیتابیس
#   commit-<زمان>.txt       کامیت جاری کد
#
# هیچ چیزی را تغییر نمی‌دهد؛ فقط می‌خواند.
# ============================================================
set -euo pipefail

cd "$(dirname "$0")"

DB_CONTAINER="${DB_CONTAINER:-repert-mysql}"
OUT="backups"
TS="$(date +%Y%m%d-%H%M%S)"

mkdir -p "$OUT"
chmod 700 "$OUT" 2>/dev/null || true

echo "🗄️  بکاپ‌گیری — $TS"

if ! docker inspect "$DB_CONTAINER" >/dev/null 2>&1; then
    echo "❌ کانتینر «$DB_CONTAINER» پیدا نشد."
    echo "   با docker ps نامش را ببین و این‌طور اجرا کن: DB_CONTAINER=نام bash backup-docker.sh"
    exit 1
fi

# ── ۱) دامپ دیتابیس ───────────────────────────────────────────────────────────
# رمز از داخل خود کانتینر خوانده می‌شود تا در تاریخچه‌ی شل هاست نیفتد.
echo "   ⏳ mysqldump..."
docker exec "$DB_CONTAINER" sh -c '
    exec mysqldump --single-transaction --quick --routines --triggers \
        --default-character-set=utf8mb4 \
        -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"
' | gzip -9 > "$OUT/db-$TS.sql.gz"

gzip -t "$OUT/db-$TS.sql.gz"

TABLES_IN_DUMP="$(gunzip -c "$OUT/db-$TS.sql.gz" | grep -c '^CREATE TABLE' || true)"

if [ "$TABLES_IN_DUMP" -lt 5 ]; then
    echo "❌ دامپ مشکوک است: فقط $TABLES_IN_DUMP جدول در آن هست. ادامه نده."
    exit 1
fi

echo "   ✅ دامپ سالم است — $TABLES_IN_DUMP جدول."

# ── ۲) شمارش دقیق رکوردها ─────────────────────────────────────────────────────
SQL=""
for t in users roles provinces representatives departments department_fields \
         categories category_fields field_options reports bot_states \
         monthly_statuses broadcast_messages settings; do
    [ -n "$SQL" ] && SQL="$SQL UNION ALL "
    SQL="$SQL SELECT '$t' AS جدول, COUNT(*) AS تعداد FROM \`$t\`"
done

printf '%s;\n' "$SQL" | docker exec -i "$DB_CONTAINER" sh -c '
    exec mysql -B -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"
' > "$OUT/rowcounts-$TS.txt" 2>/dev/null || echo "(شمارش رکوردها ناموفق بود)" > "$OUT/rowcounts-$TS.txt"

# ── ۳) فایل‌های آپلودی (volume) ───────────────────────────────────────────────
STORAGE_VOL="$(docker volume ls -q | grep -E 'repert_storage$' | head -1 || true)"

if [ -n "$STORAGE_VOL" ]; then
    echo "   ⏳ بسته‌بندی volume «$STORAGE_VOL»..."
    docker run --rm \
        -v "$STORAGE_VOL":/data:ro \
        -v "$PWD/$OUT":/backup \
        alpine tar -czf "/backup/files-$TS.tar.gz" -C /data . 
    echo "   ✅ فایل‌های آپلودی بسته‌بندی شد."
else
    echo "   ⚠️  volume فایل‌های آپلودی پیدا نشد (docker volume ls را نگاه کن)."
fi

# ── ۴) رازها و کامیت ──────────────────────────────────────────────────────────
if [ -f .env.docker ]; then
    cp .env.docker "$OUT/env-$TS.bak"
    chmod 600 "$OUT/env-$TS.bak"
    echo "   ✅ کپی .env.docker گرفته شد (شامل APP_KEY — مراقب باش)."
fi

git rev-parse HEAD > "$OUT/commit-$TS.txt" 2>/dev/null || echo unknown > "$OUT/commit-$TS.txt"

echo ""
echo "📦 خروجی:"
ls -lh "$OUT" | grep "$TS" || true
echo ""
echo "📊 تعداد رکوردها (قبل از deploy):"
cat "$OUT/rowcounts-$TS.txt"
echo ""
echo "⚠️  بکاپ روی همان سروری است که قرار است تغییر کند."
echo "   یک نسخه را به بیرون ببر، مثلاً از روی لپ‌تاپ:"
echo "   scp user@server:$(pwd)/$OUT/db-$TS.sql.gz ."
echo ""
echo "↩️  بازگردانی (فقط در صورت نیاز):"
echo "   gunzip -c $OUT/db-$TS.sql.gz | docker exec -i $DB_CONTAINER sh -c 'exec mysql -u root -p\"\$MYSQL_ROOT_PASSWORD\" \"\$MYSQL_DATABASE\"'"
