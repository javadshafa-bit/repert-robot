<?php

namespace App\Services\Ai;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * نقطه‌ی ورود ربات به هوش مصنوعی.
 * BotController فقط این را صدا می‌زند:
 *     $answer = app(AiAssistant::class)->reply($chatId, $text, $userName);
 *
 * فرض: TenantContext پیش از این ست شده (در وبهوک per-tenant همین‌طور است).
 */
class AiAssistant
{
    public function __construct(
        protected OpenAiClient $client,
        protected AiMemoryService $memory,
    ) {}

    public static function enabled(): bool
    {
        return (string) Setting::get('ai_enabled') === '1' && config('ai.api_key');
    }

    public function reply(string $chatId, string $userText, ?string $userName = null): string
    {
        $userText = trim(mb_substr($userText, 0, 4000));
        if ($userText === '') {
            return 'پیامتان خالی بود.';
        }

        if ($this->memory->countToday($chatId) >= config('ai.daily_limit_per_chat')) {
            return 'سقف پرسش‌های امروز شما پر شده است. لطفاً فردا دوباره امتحان کنید.';
        }

        try {
            // ۱) بردار پرسش — هم برای جست‌وجو و هم برای ذخیره استفاده می‌شود (یک درخواست کمتر)
            $queryVector = $this->client->embed([$userText])[0] ?? [];

            // ۲) حافظه‌ی کوتاه‌مدت + بازیابی بلندمدت
            $history = $this->memory->recentHistory($chatId);
            $recentIds = $this->memory->recentIds($chatId);
            $recalled = $queryVector ? $this->memory->recall($chatId, $queryVector, $recentIds)
                                     : ['memories' => collect(), 'knowledge' => collect()];

            // ۳) ساخت پرامپت
            $messages = [['role' => 'system', 'content' => $this->systemPrompt($userName, $recalled)]];
            foreach ($history as $h) {
                $messages[] = $h;
            }
            $messages[] = ['role' => 'user', 'content' => $userText];

            // ۴) پاسخ مدل
            $answer = $this->client->chat($messages);
            if ($answer === '') {
                $answer = 'متأسفم، پاسخی آماده نشد. لطفاً دوباره بپرسید.';
            }

            // ۵) ذخیره در حافظه — پیام کاربر با بردارش، پاسخ هم با بردار خودش
            $this->memory->storeMessage($chatId, 'user', $userText, $queryVector ?: null);
            $answerVector = $this->safeEmbed($answer);
            $this->memory->storeMessage($chatId, 'assistant', $answer, $answerVector);

            return $answer;
        } catch (Throwable $e) {
            Log::warning('AI reply failed', ['chat_id' => $chatId, 'error' => $e->getMessage()]);

            return 'در حال حاضر اتصال به دستیار هوشمند برقرار نیست. لطفاً کمی بعد دوباره تلاش کنید.';
        }
    }

    protected function systemPrompt(?string $userName, array $recalled): string
    {
        $base = trim((string) Setting::get('ai_system_prompt')) ?: config('ai.default_system_prompt');

        $parts = [$base];
        $parts[] = 'تاریخ امروز: '.now('Asia/Tehran')->toDateString().'.';
        if ($userName) {
            $parts[] = "نام کاربر: {$userName}.";
        }

        if ($recalled['knowledge']->isNotEmpty()) {
            $parts[] = "اطلاعات مرتبط از پایگاه دانش سازمان:\n"
                .$recalled['knowledge']->map(fn ($k) => '- '.$k->content)->implode("\n");
        }

        if ($recalled['memories']->isNotEmpty()) {
            $parts[] = "از گفتگوهای قبلی همین کاربر (ممکن است کهنه باشد):\n"
                .$recalled['memories']
                    ->sortBy('id')
                    ->map(fn ($m) => '- ['.($m->role === 'user' ? 'کاربر' : 'تو').' / '.$m->created_at?->toDateString().'] '.mb_substr($m->content, 0, 600))
                    ->implode("\n");
        }

        return implode("\n\n", $parts);
    }

    protected function safeEmbed(string $text): ?array
    {
        try {
            return $this->client->embed([$text])[0] ?? null;
        } catch (Throwable) {
            return null;   // پاسخ بدون بردار هم ذخیره می‌شود؛ فقط در جست‌وجوی بلندمدت نمی‌آید
        }
    }
}
