<?php

/*
|--------------------------------------------------------------------------
| تنظیمات اتصال به OpenAI
|--------------------------------------------------------------------------
| کلید API و پروکسی سطح پلتفرم‌اند (در .env)، نه per-tenant.
| روشن/خاموش بودن و پرامپت هر سازمان از جدول settings خوانده می‌شود:
|   Setting::get('ai_enabled')        → '1' یا '0'
|   Setting::get('ai_system_prompt')  → متن نقش ربات (اختیاری)
|
| سرور داخل ایران است و OpenAI آی‌پی ایران را مسدود می‌کند؛ پس یکی از این دو لازم است:
|   ۱) OPENAI_PROXY  → پروکسی HTTP/SOCKS روی سرور خارج (مثل http://user:pass@1.2.3.4:3128 یا socks5h://...)
|   ۲) OPENAI_BASE_URL → یک relay/reverse-proxy سازگار با OpenAI روی سرور خارج
*/

return [
    'api_key'  => env('OPENAI_API_KEY'),
    'base_url' => rtrim(env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
    'proxy'    => env('OPENAI_PROXY'),          // خالی = بدون پروکسی
    'timeout'  => (int) env('OPENAI_TIMEOUT', 45),

    'chat_model'      => env('OPENAI_CHAT_MODEL', 'gpt-5.4-mini'),
    'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
    'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 800),

    // حافظه
    'memory' => [
        'recent_messages'   => 10,   // چند پیام آخر که مستقیم در پرامپت می‌رود (حافظه‌ی کوتاه‌مدت)
        'recall_top_k'      => 5,    // چند تکه‌ی مرتبط از گذشته‌ی همین کاربر (RAG)
        'knowledge_top_k'   => 4,    // چند تکه از پایگاه دانش سازمان (RAG)
        'min_similarity'    => 0.30, // زیر این شباهت کسینوسی، تکه نادیده گرفته می‌شود
        'scan_limit'        => 1500, // سقف ردیف‌هایی که برای جست‌وجوی برداری بررسی می‌شوند
        'chunk_chars'       => 1200, // اندازه‌ی هر تکه‌ی دانش هنگام import
    ],

    // سقف استفاده‌ی هر کاربر در روز (جلوگیری از هزینه‌ی ناخواسته)
    'daily_limit_per_chat' => (int) env('AI_DAILY_LIMIT_PER_CHAT', 50),

    'default_system_prompt' => 'تو دستیار ربات گزارش‌گیری حوزه هنری هستی. به فارسی روان، کوتاه و محترمانه پاسخ بده. '
        .'اگر از «اطلاعات مرتبط» چیزی به دردت خورد از آن استفاده کن؛ اگر پاسخ را نمی‌دانی، حدس نزن و صادقانه بگو. '
        .'برای ثبت گزارش، کاربر را به دکمه‌ی «ثبت گزارش جدید» در منوی اصلی راهنمایی کن. '
        .'پاسخ در پیام‌رسان نمایش داده می‌شود: از تیتر، جدول و کد استفاده نکن؛ برای پررنگ فقط یک ستاره به کار ببر (*متن*).',
];
