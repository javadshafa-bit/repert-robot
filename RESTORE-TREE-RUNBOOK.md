# Runbook: بازگرداندن درختواره‌ی یک سازمان از دامپ شبانه

**مورد:** سازمان «یادا» (`asltop@gmail.com` — امیر صادقی اصل) — فیلد/گزینه‌هایی از درختواره حذف شده و باید برگردند.
**تاریخ:** ۱۴۰۵/۰۷/۱۸ (2026-10-10)

---

## ⚠️ دو قاعده‌ی غیرقابل‌مذاکره

**۱. `php artisan tree:import` را اجرا نکن.**
آن دستور اول `categories` سازمان را `delete` می‌کند و در `reports` کلید `category_id` با `cascadeOnDelete` تعریف شده (`database/migrations/2026_05_01_122856_create_reports_table.php`). نتیجه: **همه‌ی گزارش‌های ثبت‌شده‌ی آن سازمان پاک می‌شوند** و با برگشتن دسته‌بندی‌ها هم برنمی‌گردند. این باگ در فاز راه‌اندازی دیده نشد چون آن موقع گزارشی وجود نداشت. (این را جداگانه باید درست کنیم — بخش «کار باقی‌مانده» در انتهای همین فایل.)

**۲. دامپ را روی دیتابیس production بازگردانی نکن.**
دامپ شبانه کل دیتابیس است. برگرداندنش روی production یعنی عقب بردن داده‌ی **همه‌ی** سازمان‌ها: گزارش‌های امروز، پرداخت‌ها، کدهای OTP، اشتراک‌ها. دامپ فقط در یک دیتابیس موقت باز می‌شود و از آن فقط ردیف‌های لازم بیرون کشیده می‌شود.

---

## چرا این روش امن است

- `tree:import` موقع insert، `id` اصلی ردیف‌ها را حفظ می‌کند و ما هم همین کار را می‌کنیم؛ پس ارجاع‌های موجود نمی‌شکنند.
- `reports.data` هنگام ثبت گزارش، `{field_id, label, type, value}` را به‌صورت **snapshot** داخل خودش ذخیره می‌کند (`BotController` خط ۹۱۸ و اطرافش). یعنی گزارش‌های قدیمی خودکفا هستند و حذف/برگشت فیلد به آن‌ها آسیب نمی‌زند.
- ما **هیچ DELETE ای** روی production نمی‌زنیم؛ فقط ردیف‌های غایب را `INSERT IGNORE` می‌کنیم. پس هیچ cascade ای فعال نمی‌شود.

---

## پیش‌نیاز

```bash
cd /home/simorgh/stacks/repert-robot
```

یک میان‌بر برای کوتاه شدن دستورها (در همان ترمینال یک‌بار اجرا کن):

```bash
sqlp () { docker compose --env-file .env.docker exec -T mysql \
  sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" --default-character-set=utf8mb4 "$@"' -- "$@"; }
```

---

## گام ۰ — بکاپ وضعیت فعلی (قبل از هر کاری)

اگر چیزی در گام‌های بعد اشتباه رفت، باید بتوانی به همین لحظه برگردی.

```bash
bash docker/backup.sh
ls -lh /home/simorgh/backups/repert/ | tail -5
```

---

## گام ۱ — پیدا کردن `tenant_id` سازمان یادا

```bash
sqlp repert -e "SELECT id, name, email, status, created_at FROM tenants
                WHERE email='asltop@gmail.com' OR name LIKE '%یادا%';"
```

عدد ستون `id` را یادداشت کن. در ادامه هرجا `:TID` نوشته شده، همان عدد را بگذار.

---

## گام ۲ — انتخاب دامپ درست

تغییر **امروز** انجام شده، پس دامپ دیشب ساعت ۰۳:۰۰ نسخه‌ی سالم است.

```bash
ls -lt /home/simorgh/backups/repert/db-*.sql.gz | head -5
```

جدیدترین فایلی را بردار که **تاریخش دیشب** است (نه امروز، اگر امروز دستی بکاپ گرفته‌ای). مسیرش را در `DUMP` بگذار:

```bash
DUMP=/home/simorgh/backups/repert/db-2026-10-09-0300.sql.gz   # ← نام واقعی را بگذار
gunzip -t "$DUMP" && echo "دامپ سالم است"
```

---

## گام ۳ — باز کردن دامپ در یک دیتابیس موقت

```bash
sqlp -e "CREATE DATABASE IF NOT EXISTS repert_restore
         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

gunzip -c "$DUMP" | docker compose --env-file .env.docker exec -T mysql \
  sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" --default-character-set=utf8mb4 repert_restore'

sqlp repert_restore -e "SELECT COUNT(*) AS cats FROM categories;"
```

> دامپ با `mysqldump ... repert` (بدون `--databases`) گرفته می‌شود، پس داخلش `USE` یا `CREATE DATABASE` نیست و بی‌خطر داخل `repert_restore` می‌نشیند.

---

## گام ۴ — مقایسه‌ی خشک (هیچ چیزی تغییر نمی‌کند)

ببین چه چیزی حذف شده و دقیقاً چه چیزی برگردانده می‌شود:

```bash
sqlp -e "
SET @t := :TID;

SELECT 'categories حذف‌شده' AS bucket, r.id, r.name AS label
FROM repert_restore.categories r
LEFT JOIN repert.categories p ON p.id = r.id
WHERE r.tenant_id = @t AND p.id IS NULL

UNION ALL
SELECT 'category_fields حذف‌شده', r.id, r.label
FROM repert_restore.category_fields r
LEFT JOIN repert.category_fields p ON p.id = r.id
WHERE r.tenant_id = @t AND p.id IS NULL

UNION ALL
SELECT 'field_options حذف‌شده', r.id, r.label
FROM repert_restore.field_options r
LEFT JOIN repert.field_options p ON p.id = r.id
WHERE r.tenant_id = @t AND p.id IS NULL

UNION ALL
SELECT 'departments حذف‌شده', r.id, r.name
FROM repert_restore.departments r
LEFT JOIN repert.departments p ON p.id = r.id
WHERE r.tenant_id = @t AND p.id IS NULL
ORDER BY bucket, id;
"
```

و برای اینکه مطمئن شوی چیزی که **بعد از** دامپ درست اضافه شده را خراب نمی‌کنیم (این‌ها دست‌نخورده می‌مانند، فقط برای اطلاع):

```bash
sqlp -e "
SET @t := :TID;
SELECT 'بعد از دامپ اضافه شده (دست نمی‌زنیم)' AS note, p.id, p.label
FROM repert.category_fields p
LEFT JOIN repert_restore.category_fields r ON r.id = p.id
WHERE p.tenant_id = @t AND r.id IS NULL;
"
```

### 🛑 اینجا توقف کن

خروجی گام ۴ را بخوان. اگر در لیست «حذف‌شده» چیزی هست که **عمداً** حذف کرده‌ای و نمی‌خواهی برگردد، به من بگو تا پچ را انتخابی کنیم (با `WHERE id IN (...)`). وگرنه برو گام ۵.

---

## گام ۵ — اعمال پچ

ترتیب insert مهم است و بین `category_fields.parent_option_id` و `field_options.field_id` وابستگی حلقه‌ای وجود دارد، پس چک FK موقتاً خاموش می‌شود. همه‌چیز در یک تراکنش است.

```bash
sqlp -e "
SET @t := :TID;
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

INSERT IGNORE INTO repert.departments
  SELECT * FROM repert_restore.departments WHERE tenant_id = @t;

INSERT IGNORE INTO repert.categories
  SELECT * FROM repert_restore.categories WHERE tenant_id = @t;

INSERT IGNORE INTO repert.category_fields
  SELECT * FROM repert_restore.category_fields WHERE tenant_id = @t;

INSERT IGNORE INTO repert.field_options
  SELECT * FROM repert_restore.field_options WHERE tenant_id = @t;

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
"
```

> `INSERT IGNORE` فقط ردیف‌هایی را می‌نویسد که `id` شان در production نیست. ردیف‌های موجود (از جمله هر تغییر درستی که بعد از دامپ انجام شده) دست‌نخورده می‌مانند.
>
> `SELECT *` به یکسان بودن ترتیب ستون‌ها در دو دیتابیس وابسته است. چون دامپ از همین نسخه‌ی schema گرفته شده، یکسان است. اگر خطای `Column count doesn't match` گرفتی، به من بگو تا نسخه‌ی با نام‌بردن ستون‌ها را بدهم.

---

## گام ۶ — بررسی یکپارچگی

```bash
sqlp -e "
SET @t := :TID;

-- باید صفر باشد: فیلدی که به دسته‌بندی ناموجود اشاره کند
SELECT COUNT(*) AS یتیم_field_category FROM repert.category_fields f
LEFT JOIN repert.categories c ON c.id = f.category_id
WHERE f.tenant_id = @t AND c.id IS NULL;

-- باید صفر باشد: گزینه‌ای که به فیلد ناموجود اشاره کند
SELECT COUNT(*) AS یتیم_option_field FROM repert.field_options o
LEFT JOIN repert.category_fields f ON f.id = o.field_id
WHERE o.tenant_id = @t AND f.id IS NULL;

-- باید صفر باشد: زیرفیلدی که به گزینه/فیلد والد ناموجود اشاره کند
SELECT COUNT(*) AS یتیم_parent FROM repert.category_fields f
WHERE f.tenant_id = @t
  AND (
    (f.parent_option_id IS NOT NULL
      AND NOT EXISTS (SELECT 1 FROM repert.field_options o WHERE o.id = f.parent_option_id))
    OR
    (f.parent_field_id IS NOT NULL
      AND NOT EXISTS (SELECT 1 FROM repert.category_fields p WHERE p.id = f.parent_field_id))
  );

-- شمارش نهایی برای مقایسه با دامپ
SELECT
  (SELECT COUNT(*) FROM repert.categories      WHERE tenant_id=@t) AS cats,
  (SELECT COUNT(*) FROM repert.category_fields WHERE tenant_id=@t) AS fields,
  (SELECT COUNT(*) FROM repert.field_options   WHERE tenant_id=@t) AS opts;
"
```

هر سه عدد «یتیم» باید `0` باشند. اگر نبود، **ادامه نده** و خروجی را برای من بفرست.

---

## گام ۷ — پاک کردن مکالمه‌های نیمه‌کاره‌ی همین سازمان

`bot_states.field_queue` شناسه‌ی خام فیلدها را نگه می‌دارد. هر نماینده‌ای که همین حالا وسط پر کردن گزارش است، صفش با درختواره‌ی قبل از بازگردانی ساخته شده و ممکن است گیج شود. **فقط برای همین سازمان** ریست کن:

```bash
sqlp -e "
SET @t := :TID;
SELECT COUNT(*) AS مکالمه_نیمه_کاره FROM repert.bot_states
WHERE tenant_id = @t AND step <> 'idle';
"
```

اگر عدد کم بود، بهتر است چند دقیقه صبر کنی یا به امیر صادقی اصل خبر بدهی. برای ریست:

```bash
sqlp -e "
SET @t := :TID;
UPDATE repert.bot_states
SET step='idle', draft_data=NULL, field_queue=NULL, category_id=NULL, department_id=NULL
WHERE tenant_id = @t AND step <> 'idle';
"
```

---

## گام ۸ — تست و پاک‌سازی

۱. از پنل یادا وارد شو و درختواره را چشمی چک کن.
۲. در ربات یادا یک `/start` → ثبت گزارش بزن و ببین فیلدهای برگشته ظاهر می‌شوند.
۳. یک گزارش قدیمی را در پنل باز کن و مطمئن شو پاسخ‌هایش سالم است.
۴. دیتابیس موقت را حذف کن (حاوی کل داده‌ی همه‌ی سازمان‌هاست، نباید بماند):

```bash
sqlp -e "DROP DATABASE repert_restore;"
```

۵. یک export از درختواره‌ی سالم بگیر و نگه دار:

```bash
docker compose --env-file .env.docker exec -T app \
  php artisan tree:export --tenant=:TID --output=storage/backups/tree-yada-$(date +%F).json
```

---

## کار باقی‌مانده (باید در یک تغییر کد جدا انجام شود)

۱. **`tree:import` خطرناک است.** باید یا از `delete` روی `categories` دست بردارد و به‌جایش `upsert` کند، یا قبل از اجرا بشمارد که چند گزارش روی آن دسته‌بندی‌ها هست و با پیام واضح متوقف شود. فعلاً روی هر سازمانی که گزارش دارد نباید اجرا شود.

۲. **`reports.category_id` با `cascadeOnDelete` تعریف شده** — یعنی حذف یک دسته‌بندی از پنل ادمین هم بی‌صدا گزارش‌ها را می‌برد. این را به `restrictOnDelete` تغییر بده تا حذف دسته‌بندیِ دارای گزارش از اساس ممکن نباشد، و در UI پیام مناسب بدهد.

۳. **درختواره هیچ تاریخچه‌ای ندارد.** برای اینکه دفعه‌ی بعد نیازی به دامپ نباشد:
   - `SoftDeletes` روی `categories`, `category_fields`, `field_options` (حداقل این؛ ارزان‌ترین راه‌حل است و همین مورد را کامل حل می‌کرد)
   - یا یک جدول `tree_snapshots` که قبل از هر تغییر ساختاری در `CategoryController`، نسخه‌ی JSON درختواره‌ی آن سازمان را با نام کاربر و زمان ذخیره کند، و یک دکمه‌ی «بازگردانی به نسخه‌ی قبل» در پنل.

۴. **عملیات حساس سوپرادمین روی داده‌ی مستأجر لاگ ندارد.** جدول `subscription_logs` برای اشتراک وجود دارد ولی تغییر درختواره‌ی یک سازمان توسط سوپرادمین هیچ‌جا ثبت نمی‌شود.
