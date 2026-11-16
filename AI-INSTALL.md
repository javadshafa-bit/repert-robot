# ماژول هوش مصنوعی با حافظه‌ی RAG — راهنمای نصب

## فایل‌ها (همه را با همین مسیرها در ریشه‌ی پروژه کپی کن)
```
config/ai.php
database/migrations/2026_11_15_000001_create_ai_memories_table.php
app/Models/AiMemory.php
app/Services/Ai/OpenAiClient.php        ← اتصال به OpenAI (با پروکسی)
app/Services/Ai/AiMemoryService.php     ← حافظه‌ی کوتاه‌مدت + جست‌وجوی برداری
app/Services/Ai/AiAssistant.php         ← تنها چیزی که BotController صدا می‌زند
app/Console/Commands/AiKnowledgeImport.php   → php artisan ai:knowledge
app/Console/Commands/AiTest.php              → php artisan ai:test
```
هیچ پکیج composer تازه‌ای لازم نیست؛ فقط `Http` خود لاراول.

## حافظه چطور کار می‌کند
| لایه | چه چیزی | کجا |
|---|---|---|
| کوتاه‌مدت | ۱۰ پیام آخر همان کاربر، عیناً در پرامپت | `ai_memories` با `kind=message` |
| بلندمدت (RAG) | ۵ پیام قدیمی‌ترِ مرتبط با پرسش فعلی، با شباهت برداری | همان جدول |
| پایگاه دانش | ۴ تکه‌ی مرتبط از متن‌هایی که مدیر بارگذاری کرده (آیین‌نامه، FAQ، راهنمای گزارش) | `chat_id = null`, `kind=knowledge` |

بردارها (`text-embedding-3-small`) در خود MySQL ذخیره می‌شوند و شباهت در PHP حساب می‌شود؛
سرویس جانبی (Qdrant، pgvector و…) لازم نیست. اگر روزی یک سازمان ده‌ها هزار تکه دانش داشت، آن وقت سراغ پایگاه برداری می‌رویم.

جدول `tenant_id` دارد و مدل از `BelongsToTenant` استفاده می‌کند، پس حافظه‌ی هر سازمان جداست.

## ۱) .env روی سرور
```env
OPENAI_API_KEY=sk-...
OPENAI_CHAT_MODEL=gpt-5.4-mini
OPENAI_EMBEDDING_MODEL=text-embedding-3-small

# سرور داخل ایران است و OpenAI آی‌پی ایران را مسدود می‌کند. یکی از این دو:
OPENAI_PROXY=http://user:pass@IP-سرور-خارج:3128      # یا socks5h://user:pass@IP:1080
# OPENAI_BASE_URL=https://relay.example.com/v1       # اگر relay سازگار با OpenAI داری

AI_DAILY_LIMIT_PER_CHAT=50
```
> socks5h (با h) یعنی DNS هم از پشت پروکسی حل شود؛ برای ایران همین را بزن.
> در داکر: این متغیرها باید به سرویس `app` برسند (env_file یا environment در docker-compose).

## ۲) مایگریشن و آزمایش
```bash
php artisan migrate
php artisan config:clear
php artisan ai:test                         # اتصال خام به OpenAI از پشت پروکسی
php artisan ai:test 1 "سلام، اسم من جواده"
php artisan ai:test 1 "اسم من چی بود؟"        # باید یادش باشد
```
(در سرور داکری: `docker exec -it repert-app php artisan ...`)

## ۳) روشن کردن برای یک سازمان
در جدول `settings` همان سازمان:
- `ai_enabled` = `1`
- `ai_system_prompt` = نقش ربات (اختیاری؛ خالی = متن پیش‌فرض در `config/ai.php`)

## ۴) پایگاه دانش (اختیاری)
```bash
php artisan ai:knowledge 1 storage/app/rahnama.txt
php artisan ai:knowledge 1 storage/app/rahnama.txt --replace   # جایگزینی نسخه‌ی قبلی همان فایل
```

## ۵) وصل کردن به BotController
باید در جایی که پیام متنی در حالت `idle` می‌رسد (و دستور یا دکمه‌ی منو نیست) این را بگذاری.
اسم متد ارسال پیام را با متد خودت در BotController عوض کن.

```php
use App\Services\Ai\AiAssistant;

// داخل processMessage، در شاخه‌ی step === 'idle'، بعد از بررسی دکمه‌های منو:
if ($text === '/forget') {
    app(\App\Services\Ai\AiMemoryService::class)->forget((string) $chatId);
    $this->sendMessage($chatId, 'حافظه‌ی گفتگوی شما پاک شد.');
    return;
}

if ($state->step === 'idle' && $text !== '' && ! str_starts_with($text, '/') && AiAssistant::enabled()) {
    $repName = $state->representative_id
        ? \App\Models\Representative::find($state->representative_id)?->name
        : null;

    $answer = app(AiAssistant::class)->reply($chatId, $text, $repName);
    $this->sendMessage($chatId, $answer);
    return;
}
```
- `sendMessage(string $chatId, string $text, $replyMarkup = null)` همان متد فعلی BotController است.
- `handle()` پردازش هر update را داخل `DB::transaction` انجام می‌دهد؛ یعنی تا جواب OpenAI برسد
  (چند ثانیه) تراکنش باز می‌ماند. برای ترافیک کم مشکلی نیست؛ اگر زیاد شد، فراخوانی `reply` را به Job صف ببر.

نکته‌ها:
- فقط در `idle` وصلش کن؛ وسط پر کردن فرم گزارش، متن کاربر پاسخ فیلد است، نه سؤال از هوش مصنوعی.
- در حالت نظرسنجی (`bot_survey_mode = 1`) احتمالاً نمی‌خواهی فعال باشد؛ آنجا `ai_enabled` را ۰ بگذار.
- پاسخ OpenAI بین ۲ تا ۱۰ ثانیه طول می‌کشد. وبهوک بله منتظر می‌ماند، اما اگر ترافیک بالا رفت،
  این فراخوانی را به یک Job در صف ببر تا وبهوک سریع ۲۰۰ برگرداند.
- خطاهای OpenAI/پروکسی لاگ می‌شوند و کاربر فقط یک پیام مؤدبانه می‌بیند؛ ربات نمی‌خوابد.
