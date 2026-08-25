#!/usr/bin/env bash
# ============================================================
# backup.sh — بکاپ کامل قبل از deploy
#
#   bash backup.sh
#
# خروجی در storage/backups/:
#   db-<زمان>.sql.gz         دامپ دیتابیس
#   files-<زمان>.tar.gz      فایل‌های آپلودشده (storage/app)
#   rowcounts-<زمان>.txt     تعداد رکورد هر جدول (برای مقایسه بعد از migrate)
#   commit-<زمان>.txt        کامیت جاری کد
#
# این اسکریپت چیزی را تغییر نمی‌دهد و فقط می‌خواند.
# ============================================================
set -euo pipefail

cd "$(dirname "$0")"

TS="$(date +%Y%m%d-%H%M%S)"
DIR="storage/backups"
mkdir -p "$DIR"
chmod 700 "$DIR" 2>/dev/null || true

echo "🗄️  بکاپ‌گیری — $TS"

# اطلاعات اتصال از خود لاراول خوانده می‌شود، نه با parse دستی .env
eval "$(php -r '
require __DIR__."/vendor/autoload.php";
$app = require __DIR__."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$name = config("database.default");
$c    = config("database.connections.$name");
$q    = function ($v) { return "\x27" . str_replace("\x27", "\x27\\\x27\x27", (string) $v) . "\x27"; };
echo "BK_DRIVER=" . $q($c["driver"]   ?? "") . "\n";
echo "BK_NAME="   . $q($c["database"] ?? "") . "\n";
echo "BK_HOST="   . $q($c["host"]     ?? "127.0.0.1") . "\n";
echo "BK_PORT="   . $q($c["port"]     ?? "3306") . "\n";
echo "BK_USER="   . $q($c["username"] ?? "") . "\n";
echo "BK_PASS="   . $q($c["password"] ?? "") . "\n";
')"

echo "   دیتابیس: $BK_DRIVER — $BK_NAME"

# ── ۱) دیتابیس ────────────────────────────────────────────────────────────────
DB_FILE=""

case "$BK_DRIVER" in
    mysql|mariadb)
        DUMP=""
        for candidate in mysqldump mariadb-dump; do
            if command -v "$candidate" >/dev/null 2>&1; then DUMP="$candidate"; break; fi
        done

        if [ -z "$DUMP" ]; then
            echo "❌ mysqldump روی این سرور نیست."
            echo "   یا از پنل چابکان بکاپ دیتابیس بگیر، یا از کانتینری که mysql-client دارد اجرا کن."
            exit 1
        fi

        MYCNF="$(mktemp)"
        chmod 600 "$MYCNF"
        trap 'rm -f "$MYCNF"' EXIT
        printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword="%s"\n' \
            "$BK_HOST" "$BK_PORT" "$BK_USER" "$BK_PASS" > "$MYCNF"

        "$DUMP" --defaults-extra-file="$MYCNF" \
            --single-transaction --quick --routines --triggers \
            --default-character-set=utf8mb4 \
            "$BK_NAME" | gzip -9 > "$DIR/db-$TS.sql.gz"

        DB_FILE="$DIR/db-$TS.sql.gz"
        gzip -t "$DB_FILE"
        echo "   ✅ دامپ دیتابیس سالم است."
        ;;

    sqlite)
        # کپی امن (نه cp خام) تا فایل نیمه‌نوشته گرفته نشود
        if command -v sqlite3 >/dev/null 2>&1; then
            sqlite3 "$BK_NAME" ".backup '$DIR/db-$TS.sqlite'"
        else
            cp "$BK_NAME" "$DIR/db-$TS.sqlite"
        fi
        gzip -9 "$DIR/db-$TS.sqlite"
        DB_FILE="$DIR/db-$TS.sqlite.gz"
        echo "   ✅ فایل sqlite کپی شد."
        ;;

    *)
        echo "❌ درایور ناشناخته: $BK_DRIVER"
        exit 1
        ;;
esac

# ── ۲) شمارش رکوردها (برای مقایسه‌ی بعد از migrate) ──────────────────────────
php -r '
require __DIR__."/vendor/autoload.php";
$app = require __DIR__."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$tables = ["users","roles","provinces","representatives","departments","department_fields",
           "categories","category_fields","field_options","reports","bot_states",
           "monthly_statuses","broadcast_messages","settings","tenants","payments"];
foreach ($tables as $t) {
    try { printf("%-22s %d\n", $t, Illuminate\Support\Facades\DB::table($t)->count()); }
    catch (Throwable $e) { printf("%-22s -\n", $t); }
}
' > "$DIR/rowcounts-$TS.txt"

# ── ۳) فایل‌های آپلودشده ──────────────────────────────────────────────────────
if [ -d storage/app ]; then
    tar -czf "$DIR/files-$TS.tar.gz" --exclude='storage/app/backups' storage/app
    echo "   ✅ فایل‌های storage/app بسته‌بندی شد."
fi

# ── ۴) کامیت جاری ─────────────────────────────────────────────────────────────
git rev-parse HEAD > "$DIR/commit-$TS.txt" 2>/dev/null || echo "unknown" > "$DIR/commit-$TS.txt"

echo ""
echo "📦 خروجی:"
ls -lh "$DIR" | grep "$TS" || true
echo ""
echo "📊 تعداد رکوردها:"
cat "$DIR/rowcounts-$TS.txt"
echo ""
echo "⚠️  این فایل‌ها روی همان سروری هستند که قرار است تغییر کند."
echo "   حتماً یک نسخه را به بیرون منتقل کن، مثلاً:"
echo "   scp user@server:$(pwd)/$DB_FILE ."
