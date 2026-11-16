<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\BotState;
use App\Models\Category;
use App\Models\CategoryField;
use App\Models\Province;
use App\Models\Report;
use App\Models\Representative;
use App\Models\Setting;
use App\Support\BotText;
use App\Support\JalaliCalendar;
use Illuminate\Support\Carbon;
use Morilog\Jalali\Jalalian;

/**
 * حالت نظرسنجی ربات (تنظیم per-tenant: bot_survey_mode = 1).
 *
 * برای مراسمی که مخاطبش از قبل ثبت‌نام نکرده: هر کسی /start بزند پذیرفته است.
 *
 *   /start → خوش‌آمد + نام و نام خانوادگی → شماره همراه (دکمه یا تایپ)
 *          → سؤال‌های فرم نظرسنجی یکی‌یکی → ذخیره در reports → تشکر
 *
 * بدون منوی اصلی، انتخاب ماه/دپارتمان/دسته و پیش‌نمایش. پاسخ‌ها همان
 * گزارش‌های معمولی‌اند، پس صفحه‌ی گزارش‌ها و خروجی اکسل بدون تغییر کار می‌کنند.
 * شرکت‌کننده یک «نماینده» با نام و شماره‌ی واقعی می‌شود؛ شماره در هر سازمان
 * یکتاست، پس آمدن از گوشی دیگر رکورد تکراری نمی‌سازد.
 *
 * تکرار (survey_repeat): nightly = هر شب یک بار، once = فقط یک بار، unlimited.
 *
 * وقتی این حالت خاموش است هیچ‌کدام از متدهای این trait صدا زده نمی‌شوند.
 */
trait SurveyFlow
{
    /** شب برگزاری ساعت ۶ صبح تهران عوض می‌شود تا پاسخ بعد از نیمه‌شب هم مال همان شب باشد */
    private const SURVEY_TIMEZONE        = 'Asia/Tehran';
    private const SURVEY_NIGHT_STARTS_AT = 6;

    /** callbackهایی که در حالت نظرسنجی معنا دارند؛ بقیه (منو، ماه، پیش‌نمایش...) نادیده گرفته می‌شوند */
    private const SURVEY_CALLBACK_PREFIXES = ['opt_', 'cal', 'branch_', 'field_skip', 'field_multiple_done', 'go_back'];

    private function surveyMode(): bool
    {
        return Setting::get('bot_survey_mode', '0') === '1';
    }

    private function surveyCallbackAllowed(string $data): bool
    {
        if ($data === 'field_multiple_done_edit') return false;

        foreach (self::SURVEY_CALLBACK_PREFIXES as $prefix) {
            if (str_starts_with($data, $prefix)) return true;
        }

        return false;
    }

    /**
     * فرم نظرسنجی: همان که در تنظیمات انتخاب شده، وگرنه اولین دسته‌ی فعال.
     * اگر فرم انتخاب‌شده غیرفعال شده باشد، نظرسنجی بسته است — به فرم دیگری نمی‌پریم.
     */
    private function surveyCategory(): ?Category
    {
        $id = (int) Setting::get('survey_category_id', 0);

        if ($id) {
            return Category::where('is_active', true)->whereKey($id)->first();
        }

        return Category::where('is_active', true)->orderBy('sort_order')->orderBy('id')->first();
    }

    private function surveyRepeat(): string
    {
        $mode = Setting::get('survey_repeat', 'nightly');

        return in_array($mode, ['nightly', 'once', 'unlimited'], true) ? $mode : 'nightly';
    }

    /** لحظه‌ی شروع «شب» جاری، به وقت تهران */
    private function surveyNightStart(): Carbon
    {
        $now   = Carbon::now(self::SURVEY_TIMEZONE);
        $start = $now->copy()->setTime(self::SURVEY_NIGHT_STARTS_AT, 0);

        if ($now->lt($start)) $start->subDay();

        return $start;
    }

    private function surveyJalaliMonth(): string
    {
        return Jalalian::fromCarbon($this->surveyNightStart())->format('Y-m');
    }

    private function surveyAlreadyAnswered(Representative $rep, Category $category): bool
    {
        $repeat = $this->surveyRepeat();
        if ($repeat === 'unlimited') return false;

        $query = Report::where('representative_id', $rep->id)->where('category_id', $category->id);

        if ($repeat === 'nightly') {
            // created_at به وقت برنامه ذخیره می‌شود؛ مرز شب باید به همان منطقه برگردد
            $query->where('created_at', '>=', $this->surveyNightStart()->setTimezone(config('app.timezone')));
        }

        return $query->exists();
    }

    private function surveyVars(Representative $rep, Category $category): array
    {
        return [
            'name'     => $rep->first_name,
            'family'   => $rep->last_name,
            'fullname' => trim($rep->first_name . ' ' . $rep->last_name),
            'title'    => $category->name,
        ];
    }

    // ==========================================
    // ورودی پیام
    // ==========================================

    private function processSurveyMessage(string $chatId, BotState $state, array $message): void
    {
        $text = isset($message['text']) ? trim((string) $message['text']) : null;

        if ($text !== null && ($text === '/start' || str_starts_with($text, '/start ') || str_starts_with($text, '/start@'))) {
            $this->surveyStart($chatId, $state);
            return;
        }

        if ($state->step === 'survey_ask_name') {
            $this->surveySaveName($chatId, $state, $text);
            return;
        }

        if ($state->step === 'survey_ask_phone') {
            $this->surveySavePhone($chatId, $state, $message['contact'] ?? null, $text);
            return;
        }

        if ($state->representative_id && $state->step === 'answering_field') {
            $this->surveyHandleAnswerMessage($chatId, $state, $message, $text);
            return;
        }

        // پیام بعد از پایان نظرسنجی یا کاربر تازه‌ای که /start نزده: مثل /start
        $this->surveyStart($chatId, $state);
    }

    /**
     * پیام متنی/فایل وسط سؤال‌ها. سؤال چندگزینه‌ای فقط با دکمه جواب می‌گیرد؛
     * اگر مدیر در فرم سؤال متنی یا عکس هم گذاشته باشد، همان منطق عادی ربات کار می‌کند.
     */
    private function surveyHandleAnswerMessage(string $chatId, BotState $state, array $message, ?string $text): void
    {
        $field = $this->currentField($state);
        if (!$field) { $this->askNextField($chatId, $state); return; }

        if ($field->type === 'option') { $this->sendMessage($chatId, BotText::get('survey_use_buttons')); return; }
        if ($field->type === 'date')   { $this->sendMessage($chatId, BotText::get('err_use_calendar')); return; }

        $photo    = $message['photo'] ?? null;
        $document = $message['document'] ?? null;

        if ($photo || $document)            { $this->handleFileUpload($chatId, $photo, $document, $state, false); return; }
        if ($text !== null && $text !== '') { $this->saveAnswerAndContinue($chatId, $text, $state); return; }

        $this->sendMessage($chatId, BotText::get('survey_use_buttons'));
    }

    // ==========================================
    // شروع و ثبت‌نام
    // ==========================================

    private function surveyStart(string $chatId, BotState $state): void
    {
        $category = $this->surveyCategory();
        if (!$category) {
            $this->sendMessage($chatId, BotText::get('survey_unavailable'), ['remove_keyboard' => true]);
            return;
        }

        $this->deleteTrackedMessage($chatId, $state);
        $rep = $this->surveyKnownParticipant($chatId, $state);

        if (!$rep) {
            $state->update(['step' => 'survey_ask_name', 'auth_data' => [], 'draft_data' => [], 'field_queue' => []]);
            $this->sendMessage(
                $chatId,
                BotText::get('survey_welcome', ['title' => $category->name]) . "\n\n" . BotText::get('survey_ask_name'),
                ['remove_keyboard' => true]
            );
            return;
        }

        if ($this->surveyAlreadyAnswered($rep, $category)) {
            $this->surveySendAlreadyDone($chatId, $state, $rep, $category);
            return;
        }

        $this->sendMessage($chatId, BotText::get('survey_greeting_returning', $this->surveyVars($rep, $category)), ['remove_keyboard' => true]);
        $this->surveyBegin($chatId, $state, $category);
    }

    /** کسی که نام و شماره‌اش را قبلاً داده؛ null یعنی باید ثبت‌نام کند */
    private function surveyKnownParticipant(string $chatId, BotState $state): ?Representative
    {
        $rep = $state->representative_id ? Representative::find($state->representative_id) : null;

        if (!$rep) {
            $rep = Representative::where($this->repChatColumn(), $chatId)->first();
            if ($rep) $state->update(['representative_id' => $rep->id]);
        }

        return ($rep && filled($rep->phone_number)) ? $rep : null;
    }

    private function surveySaveName(string $chatId, BotState $state, ?string $text): void
    {
        $name = $this->surveyParseName($text);

        if (!$name) {
            $this->sendMessage($chatId, BotText::get('survey_err_name'));
            return;
        }

        [$first, $last] = $name;
        $state->update(['step' => 'survey_ask_phone', 'auth_data' => ['first_name' => $first, 'last_name' => $last]]);

        $this->sendMessage(
            $chatId,
            BotText::get('survey_ask_phone', ['name' => $first, 'fullname' => "$first $last"]),
            $this->surveyPhoneKeyboard()
        );
    }

    /**
     * «سید علی حسینی» → [سید علی, حسینی]
     *
     * کلمه‌ی آخر نام خانوادگی است؛ چون نام‌های دوبخشی (محمد رضا، فاطمه سادات)
     * از نام خانوادگی‌های دوبخشیِ با فاصله رایج‌ترند. نام کامل در هر حال درست کنار هم دیده می‌شود.
     * فقط حرف قبول می‌شود، که هم جلوی ورودی بی‌معنی را می‌گیرد و هم Markdown پیام‌ها را خراب نمی‌کند.
     */
    private function surveyParseName(?string $text): ?array
    {
        if ($text === null) return null;

        $text = strtr($text, ['ي' => 'ی', 'ك' => 'ک', 'ـ' => '']);
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        if (mb_strlen($text) < 4 || mb_strlen($text) > 80) return null;
        if (!preg_match('/^[\p{L}\p{M}\x{200C}. ]+$/u', $text)) return null;

        $parts = explode(' ', $text);
        if (count($parts) < 2) return null;

        $last  = array_pop($parts);
        $first = implode(' ', $parts);

        return [mb_substr($first, 0, 100), mb_substr($last, 0, 100)];
    }

    private function surveyPhoneKeyboard(): array
    {
        return [
            'keyboard'          => [[['text' => BotText::get('survey_btn_share_phone'), 'request_contact' => true]]],
            'resize_keyboard'   => true,
            'one_time_keyboard' => true,
        ];
    }

    private function surveySavePhone(string $chatId, BotState $state, ?array $contact, ?string $text): void
    {
        $auth  = $state->auth_data ?? [];
        $first = $auth['first_name'] ?? null;
        $last  = $auth['last_name'] ?? null;

        // نام گم شده (state ناقص) → از اول
        if (!$first) { $this->surveyStart($chatId, $state); return; }

        $raw   = $contact['phone_number'] ?? $text;
        $phone = $raw !== null ? $this->normalizeIranPhone((string) $raw) : null;

        if (!$phone) {
            $this->sendMessage($chatId, BotText::get('survey_err_phone'), $this->surveyPhoneKeyboard());
            return;
        }

        $category = $this->surveyCategory();
        $rep      = $this->surveyAttachParticipant($chatId, $state, $phone, $first, (string) $last);

        if (!$category || !$rep) {
            $this->sendMessage($chatId, BotText::get('survey_unavailable'), ['remove_keyboard' => true]);
            return;
        }

        $state->update(['representative_id' => $rep->id, 'step' => 'idle', 'auth_data' => null]);

        // همین شماره امشب از گوشی دیگری جواب داده
        if ($this->surveyAlreadyAnswered($rep, $category)) {
            $this->surveySendAlreadyDone($chatId, $state, $rep, $category);
            return;
        }

        $this->sendMessage($chatId, BotText::get('survey_registered', $this->surveyVars($rep, $category)), ['remove_keyboard' => true]);
        $this->surveyBegin($chatId, $state, $category);
    }

    /**
     * رکورد شرکت‌کننده: اگر شماره قبلاً ثبت شده همان، وگرنه رکورد بی‌شماره‌ی همین چت، وگرنه رکورد تازه.
     * chat_id در هر سازمان یکتاست، پس از رکورد دیگری که این چت را داشت جدا می‌شود.
     */
    private function surveyAttachParticipant(string $chatId, BotState $state, string $phone, string $first, string $last): ?Representative
    {
        $rep = Representative::where('phone_number', $phone)->first();

        if (!$rep) {
            $rep = Representative::where($this->repChatColumn(), $chatId)->whereNull('phone_number')->first();
            if ($rep) {
                $rep->update(['first_name' => $first, 'last_name' => $last !== '' ? $last : '—', 'phone_number' => $phone]);
            }
        }

        if (!$rep) {
            $provinceId = $this->surveyProvinceId();
            if (!$provinceId) return null;

            $rep = Representative::create([
                'province_id'  => $provinceId,
                'first_name'   => $first,
                'last_name'    => $last !== '' ? $last : '—',
                'phone_number' => $phone,
                'is_connected' => true,
            ]);
        }

        $this->attachChatToRepresentative($chatId, $rep);

        return $rep;
    }

    /** استانِ اجباری نماینده: تنظیم «استان پیش‌فرض»، وگرنه تهران، وگرنه اولین استان */
    private function surveyProvinceId(): ?int
    {
        $id = (int) Setting::get('guest_province_id', 0);
        if ($id && Province::whereKey($id)->exists()) return $id;

        $id = Province::where('name', 'تهران')->value('id') ?? Province::orderBy('id')->value('id');

        return $id ? (int) $id : (int) Province::create(['name' => 'تهران'])->id;
    }

    private function surveySendAlreadyDone(string $chatId, BotState $state, Representative $rep, Category $category): void
    {
        $state->update(['step' => 'survey_done', 'draft_data' => [], 'field_queue' => []]);

        $key = $this->surveyRepeat() === 'once' ? 'survey_already_done_once' : 'survey_already_done';
        $this->sendMessage($chatId, BotText::get($key, $this->surveyVars($rep, $category)), ['remove_keyboard' => true]);
    }

    // ==========================================
    // سؤال‌ها
    // ==========================================

    private function surveyBegin(string $chatId, BotState $state, Category $category): void
    {
        $queue = CategoryField::where('category_id', $category->id)
            ->whereNull('parent_option_id')
            ->whereNull('parent_field_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if (!$queue) {
            $state->update(['step' => 'idle']);
            $this->sendMessage($chatId, BotText::get('survey_unavailable'));
            return;
        }

        $state->update([
            'step'          => 'answering_field',
            'category_id'   => $category->id,
            'department_id' => null,
            'jalali_month'  => $this->surveyJalaliMonth(),
            'draft_data'    => [],
            'field_queue'   => $queue,
        ]);

        $this->askNextField($chatId, $state);
    }

    /** «سؤال ۲ از ۷» — شمارش از روی پاسخ‌های داده‌شده و صف باقی‌مانده */
    private function surveyProgressHeader(BotState $state): string
    {
        $answered = count(array_filter($state->draft_data ?? [], fn($item) => ($item['type'] ?? '') !== 'branch'));
        $number   = $answered + 1;
        $total    = $answered + max(1, count($state->field_queue ?? []));

        return BotText::get('survey_progress', [
            'number' => JalaliCalendar::fa($number),
            'total'  => JalaliCalendar::fa($total),
        ]);
    }

    /** دکمه‌ی کهنه‌ی یک سؤال قبلی نباید جواب سؤال فعلی حساب شود */
    private function surveyOptionIsCurrent(BotState $state, int $fieldId): bool
    {
        $current = $this->currentField($state);

        return $current !== null && $current->id === $fieldId;
    }

    /** بازگشت یک سؤال به عقب؛ روی سؤال اول کاری نمی‌کند */
    private function surveyGoBack(string $chatId, BotState $state): void
    {
        if ($state->step !== 'answering_field') return;

        $draft = $state->draft_data ?? [];
        if (!$draft) return;

        $last  = array_pop($draft);
        $queue = $state->field_queue ?? [];

        // زیرسؤال‌هایی که به‌خاطر همین پاسخ به صف آمده بودند هم برمی‌گردند بیرون
        $childIds = CategoryField::where('parent_field_id', $last['field_id'])->pluck('id')->all();
        if (!empty($last['option_id'])) {
            $childIds = array_merge($childIds, CategoryField::where('parent_option_id', $last['option_id'])->pluck('id')->all());
        }
        $queue = array_values(array_diff($queue, $childIds));

        array_unshift($queue, $last['field_id']);

        $state->update(['draft_data' => $draft, 'field_queue' => array_values(array_unique($queue))]);
        $this->deleteTrackedMessage($chatId, $state);
        $this->askNextField($chatId, $state);
    }

    /** صف خالی شد: پاسخ‌ها ذخیره و تشکر */
    private function surveyFinish(string $chatId, BotState $state): void
    {
        $rep      = Representative::find($state->representative_id);
        $category = Category::find($state->category_id);

        if (!$rep || !$category) {
            $state->update(['step' => 'idle', 'draft_data' => [], 'field_queue' => []]);
            $this->sendMessage($chatId, BotText::get('survey_unavailable'));
            return;
        }

        // اگر همین شماره هم‌زمان از گوشی دیگری فرم را تمام کرده باشد، بار دوم ثبت نمی‌شود
        if (!$this->surveyAlreadyAnswered($rep, $category)) {
            Report::create([
                'representative_id' => $rep->id,
                'department_id'     => null,
                'category_id'       => $category->id,
                'jalali_month'      => $state->jalali_month ?: $this->surveyJalaliMonth(),
                'data'              => $state->draft_data ?? [],
            ]);
        }

        $state->update(['step' => 'survey_done', 'draft_data' => [], 'field_queue' => [], 'category_id' => null]);
        $this->sendMessage($chatId, BotText::get('survey_thanks', $this->surveyVars($rep, $category)));
    }
}
