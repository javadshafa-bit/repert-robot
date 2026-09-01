<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BotState;
use App\Models\Category;
use App\Models\CategoryField;
use App\Models\Department;
use App\Models\FieldOption;
use App\Models\Province;
use App\Models\Report;
use App\Models\Representative;
use App\Models\Setting;
use App\Models\Tenant;
use App\Support\BotText;
use App\Support\JalaliCalendar;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Morilog\Jalali\Jalalian;

class BotController extends Controller
{
    private $token;
    private $apiUrl;
    private ?Tenant $tenant = null;

    /**
     * وبهوک اختصاصی یک مستأجر.
     * مستأجر از روی webhook_secret موجود در مسیر (route model binding) پیدا می‌شود؛
     * توکن ربات هم از همان رکورد خوانده می‌شود، نه از تنظیمات سراسری.
     */
    public function handle(Request $request, Tenant $tenant)
    {
        // مستأجر تاییدنشده/معلق یا بدون توکن: هیچ پردازشی نه، ولی به بله OK بده
        // تا آپدیت را بارها دوباره نفرستد.
        if (!$tenant->botIsUsable()) {
            Log::info("bot webhook ignored for tenant#{$tenant->id} (status={$tenant->status})");
            return response('OK', 200);
        }

        $this->tenant = $tenant;
        $this->token  = $tenant->bot_token;
        $this->apiUrl = "https://tapi.bale.ai/bot{$this->token}/";

        // مستأجر درخواست قبلی نباید نشت کند (Octane / queue worker) — قبل از set پاک کن.
        TenantContext::forget();
        TenantContext::set($tenant);

        $update = $request->all();
        $this->logUpdate($tenant, $update);

        // همه‌ی پردازش این آپدیت داخل یک تراکنش با قفل روی ردیف BotState انجام می‌شود
        // تا اگر بله همین آپدیت را دوباره بفرستد یا کاربر دوبار پشت‌سرهم روی دکمه‌ای بزند،
        // دو پردازش هم‌زمان state یکدیگر را خراب نکنند (علت باگ‌های قبلیِ قاطی‌شدن صف فیلدها).
        DB::transaction(function () use ($update) {
            if (isset($update['message']))               $this->processMessage($update['message']);
            elseif (isset($update['edited_message']))     $this->processEditedMessage($update['edited_message']);
            elseif (isset($update['callback_query']))     $this->processCallback($update['callback_query']);
        });

        return response('OK', 200);
    }

    /**
     * لاگ آپدیت ورودی.
     *
     * بدنه‌ی کامل آپدیت شامل شماره تلفن و متن گزارش نمایندگان است؛ حالا که چند سازمان
     * روی یک نصب‌اند، ذخیره‌ی کامل آن هم حجیم است هم حساس. در production فقط
     * شناسه و نوع آپدیت لاگ می‌شود؛ بدنه‌ی کامل فقط در محیط‌های غیر production.
     */
    private function logUpdate(Tenant $tenant, array $update): void
    {
        if (app()->environment('production')) {
            $type = match (true) {
                isset($update['message'])        => 'message',
                isset($update['edited_message']) => 'edited_message',
                isset($update['callback_query']) => 'callback_query',
                default                          => 'other',
            };

            Log::info('bot update', [
                'tenant_id' => $tenant->id,
                'update_id' => $update['update_id'] ?? null,
                'type'      => $type,
            ]);

            return;
        }

        Log::info("tenant#{$tenant->id} " . json_encode($update, JSON_UNESCAPED_UNICODE));
    }

    private function processMessage($message)
    {
        $chatId   = $message['chat']['id'];
        $text     = $message['text'] ?? null;
        $photo    = $message['photo'] ?? null;
        $document = $message['document'] ?? null;

        BotState::firstOrCreate(['chat_id' => $chatId]);
        $state = BotState::where('chat_id', $chatId)->lockForUpdate()->first();

        if ($text === '/start') return $this->handleStart($chatId, $state, $message['from'] ?? []);
        if (isset($message['contact'])) {
            return $this->handleContact($chatId, $message['contact'], $state, $message['from'] ?? []);
        }

        // منتظر شماره‌ایم و کاربر متن فرستاده: یا دارد مرحله را رد می‌کند،
        // یا نمی‌داند باید دکمه را بزند. پیام «مجاز نیستی» اینجا گمراه‌کننده بود.
        if ($state->step === 'waiting_for_contact' && !$state->representative_id) {
            $skipLabel = trim(BotText::get('btn_skip_contact'));
            if ($this->openAccessPhoneMode() === 'optional' && $text !== null && trim($text) === $skipLabel) {
                return $this->handleContactSkip($chatId, $state, $message['from'] ?? []);
            }
            return $this->sendMessage($chatId, BotText::get('err_need_contact'));
        }

        if (!$state->representative_id) return $this->sendMessage($chatId, BotText::get('error_message'));

        if (in_array($state->step, ['answering_field', 'editing_field'])) {
            $isEditing = $state->step === 'editing_field';
            if ($photo || $document) return $this->handleFileUpload($chatId, $photo, $document, $state, $isEditing);
            if ($text) return $isEditing ? $this->saveEditedAnswer($chatId, $text, $state) : $this->saveAnswerAndContinue($chatId, $text, $state);
        }

        $this->showMainMenu($chatId);
    }

    private function processEditedMessage($message)
    {
        $chatId = $message['chat']['id'];
        $text   = $message['text'] ?? null;
        $photo  = $message['photo'] ?? null;
        $state  = BotState::where('chat_id', $chatId)->lockForUpdate()->first();

        if (!$state || !$state->representative_id) return;

        // فقط در مرحله پاسخ به فیلد یا ویرایش فیلد قابل استفاده است
        if (in_array($state->step, ['answering_field', 'editing_field'])) {
            $isEditing = $state->step === 'editing_field';
            if ($photo) return $this->handleFileUpload($chatId, $photo, null, $state, $isEditing);
            if ($text)  return $isEditing
                ? $this->saveEditedAnswer($chatId, $text, $state)
                : $this->saveAnswerAndContinue($chatId, $text, $state);
            return;
        }

        // در مرحله تأیید گزارش: پیام ویرایش‌شده را به عنوان ویرایش گزارش پردازش کن
        if ($state->step === 'confirming') {
            if ($text) {
                $this->sendMessage($chatId, BotText::get('info_edited_message'));
            }
            return;
        }

        // سایر مراحل: نادیده بگیر یا راهنمایی کن
        if ($text || $photo) {
            $this->sendMessage($chatId, BotText::get('err_edit_not_supported'));
        }
    }

    private function processCallback($callbackQuery)
    {
        $chatId = $callbackQuery['message']['chat']['id'];
        $data   = $callbackQuery['data'];
        $state  = BotState::where('chat_id', $chatId)->lockForUpdate()->first();
        if (!$state || !$state->representative_id) return;

        if ($data === 'main_start_report') {
            if ($state->step !== 'idle') { $this->sendMessage($chatId, BotText::get('warn_finish_current')); return; }
            $this->deleteTrackedMessage($chatId, $state);
            $this->startReportFlow($chatId, $state);

        } elseif (str_starts_with($data, 'month_')) {
            $state->update(['jalali_month' => str_replace('month_', '', $data)]);
            $this->deleteTrackedMessage($chatId, $state);
            $this->askNextFlowStep($chatId, $state);

        } elseif (str_starts_with($data, 'department_')) {
            $state->update(['department_id' => str_replace('department_', '', $data)]);
            $this->deleteTrackedMessage($chatId, $state);
            $this->askNextFlowStep($chatId, $state);

        } elseif (str_starts_with($data, 'category_')) {
            $state->update(['category_id' => str_replace('category_', '', $data)]);
            $this->deleteTrackedMessage($chatId, $state);
            $this->askNextFlowStep($chatId, $state);

        } elseif (str_starts_with($data, 'opt_') && in_array($state->step, ['answering_field', 'editing_field'])) {
            $this->handleOptionSelected($chatId, $state, (int) str_replace('opt_', '', $data));

        } elseif (str_starts_with($data, 'cal') && in_array($state->step, ['answering_field', 'editing_field'])) {
            $this->handleCalendarCallback($chatId, $state, $data);

        } elseif (str_starts_with($data, 'branch_') && in_array($state->step, ['answering_field', 'editing_field'])) {
            $this->handleBranchSelected($chatId, $state, (int) str_replace('branch_', '', $data));

        } elseif ($data === 'field_skip' && in_array($state->step, ['answering_field', 'editing_field'])) {
            $this->handleFieldSkip($chatId, $state);

        } elseif ($data === 'field_multiple_done' && $state->step === 'answering_field') {
            $this->handleMultipleDone($chatId, $state, false);

        } elseif ($data === 'field_multiple_done_edit' && $state->step === 'editing_field') {
            $this->handleMultipleDone($chatId, $state, true);

        } elseif ($data === 'confirm_report' && $state->step === 'preview') {
            $this->saveFinalReport($chatId, $state);

        } elseif ($data === 'request_edit' && $state->step === 'preview') {
            $this->deleteTrackedMessage($chatId, $state);
            $this->showEditOptions($chatId, $state);

        } elseif (str_starts_with($data, 'edit_field_') && $state->step === 'preview') {
            $this->startEditField($chatId, $state, (int) str_replace('edit_field_', '', $data));

        } elseif ($data === 'go_back') {
            $this->handleGoBack($chatId, $state);
        }
    }

    // ==========================================
    // Flow
    // ==========================================

    private function getFlowSteps(): array
    {
        $stored = Setting::get('bot_flow_steps');
        if ($stored) { $decoded = json_decode($stored, true); if (is_array($decoded) && count($decoded) >= 1) return $decoded; }
        return ['month', 'department', 'category'];
    }

    private function startReportFlow(string $chatId, BotState $state): void
    {
        $state->update(['jalali_month' => null, 'department_id' => null, 'category_id' => null, 'draft_data' => [], 'field_queue' => []]);
        $this->askNextFlowStep($chatId, $state);
    }

    private function askNextFlowStep(string $chatId, BotState $state): void
    {
        foreach ($this->getFlowSteps() as $step) {
            if ($step === 'month'      && !$state->jalali_month)  { $state->update(['step' => 'selecting_month']);      $this->askMonth($chatId, $state);      return; }
            if ($step === 'department' && !$state->department_id) { $state->update(['step' => 'selecting_department']); $this->askDepartment($chatId, $state); return; }
            if ($step === 'category'   && !$state->category_id)   { $state->update(['step' => 'selecting_category']);   $this->askCategory($chatId, $state);   return; }
        }
        // ساختن صف از فیلدهای سطح اول (بدون والد option یا والد field)
        $category   = Category::with(['fields' => fn($q) => $q->whereNull('parent_option_id')->whereNull('parent_field_id')->orderBy('sort_order')])->find($state->category_id);
        $fieldQueue = $category->fields->pluck('id')->toArray();
        $state->update(['step' => 'answering_field', 'draft_data' => [], 'field_queue' => $fieldQueue]);
        $this->askNextField($chatId, $state);
    }

    // ==========================================
    // Back navigation
    // ==========================================

    private function handleGoBack(string $chatId, BotState $state): void
    {
        $step = $state->step;

        // بازگشت از ماه → منوی اصلی (لغو گزارش)
        if ($step === 'selecting_month') {
            $state->update(['step' => 'idle', 'jalali_month' => null]);
            $this->deleteTrackedMessage($chatId, $state);
            $this->showMainMenu($chatId);
            return;
        }

        // بازگشت از دپارتمان → مجدداً از ابتدای flow (ماه)
        if ($step === 'selecting_department') {
            $state->update(['jalali_month' => null]);
            $this->deleteTrackedMessage($chatId, $state);
            $this->askNextFlowStep($chatId, $state);
            return;
        }

        // بازگشت از دسته‌بندی → مرحله قبلی در flow (دپارتمان یا ماه)
        if ($step === 'selecting_category') {
            $state->update(['department_id' => null]);
            $this->deleteTrackedMessage($chatId, $state);
            $this->askNextFlowStep($chatId, $state);
            return;
        }

        // بازگشت از پاسخ به فیلد → یک فیلد به عقب
        if ($step === 'answering_field') {
            $draft = $state->draft_data ?? [];
            if (empty($draft)) {
                // هنوز هیچ فیلدی پاسخ داده نشده → برگرد به انتخاب دسته‌بندی
                $state->update(['category_id' => null, 'field_queue' => [], 'draft_data' => []]);
                $this->deleteTrackedMessage($chatId, $state);
                $this->askNextFlowStep($chatId, $state);
                return;
            }
            // آخرین پاسخ را بردار و فیلدش را به ابتدای صف برگردان
            $lastItem = array_pop($draft);
            $queue    = $state->field_queue ?? [];
            array_unshift($queue, $lastItem['field_id']);
            $state->update(['draft_data' => $draft, 'field_queue' => $queue]);
            $this->deleteTrackedMessage($chatId, $state);
            $this->askNextField($chatId, $state);
            return;
        }

        // پیش‌فرض: لغو و رفتن به منوی اصلی
        $this->deleteTrackedMessage($chatId, $state);
        $state->update(['step' => 'idle', 'jalali_month' => null, 'department_id' => null, 'category_id' => null, 'draft_data' => [], 'field_queue' => []]);
        $this->showMainMenu($chatId);
    }

    // ==========================================
    // Queue helpers
    // ==========================================

    private function currentField(BotState $state): ?CategoryField
    {
        $queue = $state->field_queue ?? [];
        return empty($queue) ? null : CategoryField::find($queue[0]);
    }

    private function popField(BotState $state): void
    {
        $queue = $state->field_queue ?? [];
        array_shift($queue);
        $state->update(['field_queue' => $queue]);
    }

    private function prependOptionFields(BotState $state, FieldOption $option): void
    {
        $childIds = $option->childFields()->orderBy('sort_order')->pluck('id')->toArray();
        $state->update(['field_queue' => array_values(array_unique(array_merge($childIds, $state->field_queue ?? [])))]);
    }

    /** زیرفیلدهای همیشگی فیلد والد را به ابتدای صف اضافه می‌کند (بدون شرط) */
    private function prependAlwaysChildFields(BotState $state, CategoryField $field): void
    {
        $childIds = $field->alwaysChildFields()->pluck('id')->toArray();
        if (!empty($childIds)) {
            $state->update(['field_queue' => array_values(array_unique(array_merge($childIds, $state->field_queue ?? [])))]);
        }
    }

    // ==========================================
    // Ask steps
    // ==========================================

    private function handleStart(string $chatId, BotState $state, array $from = []): void
    {
        if ($state->representative_id) {
            $rep = Representative::with('province')->find($state->representative_id);
            $this->sendMessage($chatId, BotText::get('greeting_returning', $this->repVars($rep)));
            $this->showMainMenu($chatId);
            return;
        }

        if ($this->openAccess()) {
            $mode = $this->openAccessPhoneMode();

            // «نمی‌پرسد»: هیچ سوالی نیست، هویت همان‌جا ساخته می‌شود
            if ($mode === 'none') {
                $rep = $this->resolveOpenAccessRep($chatId, $from);
                if ($rep) {
                    $state->update(['representative_id' => $rep->id, 'step' => 'idle']);
                    $rep->load('province');
                    $this->sendMessage($chatId, BotText::get('greeting_open_access', $this->repVars($rep)));
                    $this->showMainMenu($chatId);
                    return;
                }
                // نتوانستیم بسازیم (هیچ استانی تعریف نشده) — به مسیر عادی برمی‌گردیم
                // تا کاربر دست‌کم پیام روشنی ببیند، نه سکوت.
                Log::warning('open access: no province available', ['tenant' => TenantContext::id(), 'chat' => $chatId]);
            } else {
                // «اختیاری» یا «اجباری»: شماره پرسیده می‌شود ولی هر شماره‌ای پذیرفته است
                $this->askForContact($chatId, $state, $mode === 'optional');
                return;
            }
        }

        $this->askForContact($chatId, $state, false);
    }

    /** آیا ربات این سازمان برای همه باز است؟ */
    private function openAccess(): bool
    {
        return Setting::get('bot_open_access', '0') === '1';
    }

    /** در حالت آزاد، شماره پرسیده شود؟ none | optional | required */
    private function openAccessPhoneMode(): string
    {
        if (!$this->openAccess()) return 'required';   // حالت بسته: شماره تنها راه ورود است
        $mode = Setting::get('open_access_phone_mode', 'none');
        return in_array($mode, ['none', 'optional', 'required'], true) ? $mode : 'none';
    }

    /** درخواست اشتراک شماره؛ در حالت اختیاری یک دکمه‌ی «رد کردن» هم می‌گذارد */
    private function askForContact(string $chatId, BotState $state, bool $skippable): void
    {
        $rows = [[['text' => BotText::get('btn_share_contact'), 'request_contact' => true]]];
        if ($skippable) $rows[] = [['text' => BotText::get('btn_skip_contact')]];

        $this->sendMessage($chatId, BotText::get('welcome_message'), [
            'keyboard'         => $rows,
            'resize_keyboard'  => true,
            'one_time_keyboard' => true,
        ]);
        $state->update(['step' => 'waiting_for_contact']);
    }

    /** کاربر در حالت «شماره اختیاری» مرحله را رد کرد */
    private function handleContactSkip(string $chatId, BotState $state, array $from): void
    {
        $rep = $this->resolveOpenAccessRep($chatId, $from);
        if (!$rep) { $this->sendMessage($chatId, BotText::get('error_message')); return; }

        $state->update(['representative_id' => $rep->id, 'step' => 'idle']);
        $rep->load('province');
        $this->sendMessage($chatId, BotText::get('greeting_open_access', $this->repVars($rep)));
        $this->showMainMenu($chatId);
    }

    /**
     * در حالت دسترسی آزاد، هر چت خودش یک نماینده می‌شود.
     *
     * شماره خالی می‌ماند (همان قابلیت «شماره اختیاری») و نام از پروفایل بله
     * می‌آید، پس ربات هیچ سوالی نمی‌پرسد ولی گزارش‌ها همچنان به یک شخص وصل‌اند.
     * این مهم است چون reports.representative_id ستون NOT NULL است و داشبورد
     * با join روی نمایندگان آمار می‌گیرد — گزارش بی‌صاحب از آمار می‌افتاد بیرون.
     */
    private function resolveOpenAccessRep(string $chatId, array $from): ?Representative
    {
        $existing = Representative::where('chat_id', $chatId)->first();
        if ($existing) {
            if (!$existing->is_connected) $existing->update(['is_connected' => true]);
            return $existing;
        }

        // استان پیش‌فرض تنظیمات؛ اگر پاک شده باشد به اولین استان سازمان می‌افتیم
        $provinceId = (int) Setting::get('guest_province_id', 0);
        if (!$provinceId || !Province::whereKey($provinceId)->exists()) {
            $provinceId = (int) Province::orderBy('id')->value('id');
        }
        if (!$provinceId) return null;

        $first = trim((string) ($from['first_name'] ?? ''));
        $last  = trim((string) ($from['last_name'] ?? ''));
        if ($first === '' && $last === '') {
            $first = 'کاربر';
            $last  = substr($chatId, -6);
        }

        return Representative::create([
            'province_id'  => $provinceId,
            'first_name'   => mb_substr($first !== '' ? $first : 'کاربر', 0, 100),
            'last_name'    => mb_substr($last  !== '' ? $last  : '—',     0, 100),
            'phone_number' => null,
            'chat_id'      => $chatId,
            'is_connected' => true,
        ]);
    }

    private function handleContact(string $chatId, array $contact, BotState $state, array $from = []): void
    {
        $phoneNumber = (string) ($contact['phone_number'] ?? '');

        if (str_starts_with($phoneNumber, '+98'))    $phoneNumber = '0' . substr($phoneNumber, 3);
        elseif (str_starts_with($phoneNumber, '98')) $phoneNumber = '0' . substr($phoneNumber, 2);

        $phoneNumber = trim($phoneNumber);

        // نماینده‌های بدون شماره (phone_number = null) نباید با ورودی خالی مچ شوند
        if ($phoneNumber === '') {
            $this->sendMessage($chatId, BotText::get('error_message'));
            return;
        }

        $rep = Representative::with('province')->whereNotNull('phone_number')->where('phone_number', $phoneNumber)->first();

        // در حالت آزاد، شماره‌ی ناشناس هم پذیرفته است: یا رکورد این چت را
        // تکمیل می‌کنیم یا یکی تازه می‌سازیم. نام از خود contact بهتر است
        // چون کاربر آن را خودش تأیید کرده.
        if (!$rep && $this->openAccess()) {
            $rep = $this->resolveOpenAccessRep($chatId, $contact + $from);
            if ($rep && !$rep->phone_number) {
                $rep->update(['phone_number' => $phoneNumber]);
                $rep->load('province');
            }
        }

        if ($rep) {
            // یک چت فقط می‌تواند به یک نماینده وصل باشد و chat_id یکتاست.
            // اگر این چت قبلاً (مثلاً در حالت «بدون شماره») رکورد دیگری گرفته
            // باشد، اول رهایش می‌کنیم وگرنه update با خطای unique می‌ترکد.
            Representative::where('chat_id', $chatId)
                ->where('id', '!=', $rep->id)
                ->update(['chat_id' => null, 'is_connected' => false]);

            $rep->update(['chat_id' => $chatId, 'is_connected' => true]);
            $state->update(['representative_id' => $rep->id, 'step' => 'idle']);
            $this->sendMessage($chatId, BotText::get('auth_success', $this->repVars($rep)));
            $this->showMainMenu($chatId);
        } else {
            $this->sendMessage($chatId, BotText::get('error_message'));
        }
    }

    private function showMainMenu(string $chatId): void
    {
        $keyboard = ['inline_keyboard' => [[['text' => BotText::get('btn_new_report'), 'callback_data' => 'main_start_report']]]];
        $msgId    = $this->sendMessage($chatId, BotText::get('main_menu_prompt'), $keyboard);
        BotState::where('chat_id', $chatId)->update(['step' => 'idle', 'last_message_id' => $msgId]);
    }

    private function askMonth(string $chatId, BotState $state): void
    {
        $now    = Jalalian::now();
        $months = [$now->format('Y-m'), $now->subMonths(1)->format('Y-m'), $now->subMonths(2)->format('Y-m')];
        $inlineKeyboard = array_map(fn($m) => [['text' => BotText::get('btn_month_item', ['month' => $this->formatJalaliMonthName($m)]), 'callback_data' => "month_$m"]], $months);
        $inlineKeyboard[] = [['text' => BotText::get('btn_cancel'), 'callback_data' => 'go_back']];
        $msgId = $this->sendMessage($chatId, BotText::get('ask_month'), ['inline_keyboard' => $inlineKeyboard]);
        $state->update(['last_message_id' => $msgId]);
    }

    private function askDepartment(string $chatId, BotState $state): void
    {
        $departments = Department::where('is_active', true)->orderBy('sort_order')->get();
        if ($departments->isEmpty()) { $this->sendMessage($chatId, BotText::get('empty_departments')); return; }
        $inlineKeyboard = $departments->map(fn($d) => [['text' => $d->name, 'callback_data' => "department_{$d->id}"]])->toArray();
        $inlineKeyboard[] = [['text' => BotText::get('btn_back'), 'callback_data' => 'go_back']];
        $msgId = $this->sendMessage($chatId, BotText::get('ask_department'), ['inline_keyboard' => $inlineKeyboard]);
        $state->update(['last_message_id' => $msgId]);
    }

    private function askCategory(string $chatId, BotState $state): void
    {
        $categories = Category::where('is_active', true)->orderBy('sort_order')->get();
        if ($categories->isEmpty()) { $this->sendMessage($chatId, BotText::get('empty_categories')); return; }
        $inlineKeyboard = $categories->map(fn($c) => [['text' => $c->name, 'callback_data' => "category_{$c->id}"]])->toArray();
        $inlineKeyboard[] = [['text' => BotText::get('btn_back'), 'callback_data' => 'go_back']];
        $msgId = $this->sendMessage($chatId, BotText::get('ask_category'), ['inline_keyboard' => $inlineKeyboard]);
        $state->update(['last_message_id' => $msgId]);
    }

    private function askNextField(string $chatId, BotState $state): void
    {
        $state->refresh();
        $field = $this->currentField($state);
        if (!$field) { $state->update(['step' => 'preview']); $this->showPreview($chatId, $state); return; }

        if ($field->type === 'option') {
            $this->askOptionField($chatId, $state, $field);
        } elseif ($field->type === 'date') {
            $this->askDateField($chatId, $state, $field);
        } else {
            $navKeyboard = ['inline_keyboard' => [$this->fieldNavRow($field)]];
            $msgId = $this->sendMessage($chatId, $this->buildFieldPrompt($field), $navKeyboard);
            $state->update(['last_message_id' => $msgId, 'step' => 'answering_field']);
        }
    }

    private function askOptionField(string $chatId, BotState $state, CategoryField $field): void
    {
        $options = $field->options;
        if ($options->isEmpty()) { $this->popField($state); $this->prependAlwaysChildFields($state, $field); $this->askNextField($chatId, $state); return; }

        $prompt = BotText::get('field_option_header') . "\n\n🔹 *{$field->label}*";
        if ($field->description) $prompt .= "\n📝 _{$field->description}_";

        $inlineKeyboard   = $options->map(fn($o) => [['text' => $o->label, 'callback_data' => "opt_{$o->id}"]])->toArray();
        $inlineKeyboard[] = $this->fieldNavRow($field);
        $msgId = $this->sendMessage($chatId, $prompt, ['inline_keyboard' => $inlineKeyboard]);
        $state->update(['last_message_id' => $msgId, 'step' => 'answering_field']);
    }

    // ==========================================
    // Answer handlers
    // ==========================================

    private function handleOptionSelected(string $chatId, BotState $state, int $optionId): void
    {
        $option = FieldOption::with(['field', 'childFields'])->find($optionId);
        if (!$option) return;

        $draft   = $state->draft_data ?? [];
        $draft[] = ['field_id' => $option->field->id, 'label' => $option->field->label, 'type' => 'option', 'value' => $option->label, 'option_id' => $option->id];

        $this->popField($state);
        $this->prependAlwaysChildFields($state, $option->field);

        // اگر همه فرزندان از نوع option باشند و بیش از یکی باشند،
        // به‌جای افزودن همه به صف، یک منوی انتخاب شاخه نشان می‌دهیم
        $childFields = $option->childFields()->orderBy('sort_order')->get();

        if ($childFields->count() > 1 && $childFields->every(fn($f) => $f->type === 'option')) {
            $state->update(['draft_data' => $draft]);
            $this->deleteTrackedMessage($chatId, $state);
            $this->askBranchSelection($chatId, $state, $childFields, $option->label);
            return;
        }

        // حالت عادی: تمام فرزندان را به ترتیب به صف اضافه می‌کنیم
        $this->prependOptionFields($state, $option);
        $state->update(['draft_data' => $draft]);

        $this->deleteTrackedMessage($chatId, $state);
        $this->askNextField($chatId, $state);
    }

    /** منوی انتخاب شاخه: وقتی چند فیلد option فرزند داریم، کاربر فقط یکی را انتخاب می‌کند */
    private function askBranchSelection(string $chatId, BotState $state, $childFields, string $parentLabel): void
    {
        $prompt = BotText::get('field_option_header') . "\n\n🔹 *{$parentLabel}*";
        $inlineKeyboard   = $childFields->map(fn($f) => [['text' => $f->label, 'callback_data' => "branch_{$f->id}"]])->toArray();
        $inlineKeyboard[] = [['text' => BotText::get('btn_back'), 'callback_data' => 'go_back']];
        $msgId = $this->sendMessage($chatId, $prompt, ['inline_keyboard' => $inlineKeyboard]);
        $state->update(['last_message_id' => $msgId]);
    }

    /** وقتی کاربر یک شاخه را انتخاب کرد، انتخاب را ثبت کرده و فقط همان فیلد را به صف اضافه می‌کند */
    private function handleBranchSelected(string $chatId, BotState $state, int $fieldId): void
    {
        $field = CategoryField::find($fieldId);
        if (!$field) { $this->sendMessage($chatId, BotText::get('err_option_invalid')); $this->askNextField($chatId, $state); return; }

        // ثبت انتخاب شاخه در draft_data
        $draft   = $state->draft_data ?? [];
        $draft[] = ['field_id' => $field->id, 'label' => $field->label, 'type' => 'branch', 'value' => $field->label];

        $queue = $state->field_queue ?? [];
        array_unshift($queue, $fieldId);
        $state->update(['draft_data' => $draft, 'field_queue' => array_values(array_unique($queue))]);
        $this->deleteTrackedMessage($chatId, $state);
        $this->askNextField($chatId, $state);
    }

    private function saveAnswerAndContinue(string $chatId, string $text, BotState $state): void
    {
        $field = $this->currentField($state);
        if (!$field) { $this->askNextField($chatId, $state); return; }

        if ($field->type === 'photo') { $this->sendMessage($chatId, BotText::get('err_need_photo')); return; }
        if ($field->type === 'date')  { $this->sendMessage($chatId, BotText::get('err_use_calendar')); return; }

        if ($field->type === 'link') {
            // مسیر URL می‌تواند شامل حروف فارسی یا کاراکترهای percent-encoded باشد (\S = هر کاراکتر غیر فاصله)
            if (!preg_match('/^(?:https?:\/\/)?(?:www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z]{2,10}(?::\d+)?(?:[\/?#]\S*)?$/iu', trim($text))) {
                $this->sendMessage($chatId, BotText::get('err_invalid_link'));
                return;
            }
        }

        if ($field->is_multiple) {
            $this->appendToMultiple($chatId, $state, $field, $text, false);
        } else {
            $draft   = $state->draft_data ?? [];
            $draft[] = ['field_id' => $field->id, 'label' => $field->label, 'type' => $field->type, 'value' => $text];
            $this->popField($state);
            $this->prependAlwaysChildFields($state, $field);
            $state->update(['draft_data' => $draft]);
            $this->deleteTrackedMessage($chatId, $state);
            $this->askNextField($chatId, $state);
        }
    }

    private function handleFileUpload(string $chatId, $photo, $document, BotState $state, bool $isEditing = false): void
    {
        $field = $this->currentField($state);
        if (!$field) { $this->askNextField($chatId, $state); return; }

        if ($field->type === 'date') { $this->sendMessage($chatId, BotText::get('err_use_calendar')); return; }
        if (in_array($field->type, ['text', 'link'])) { $this->sendMessage($chatId, BotText::get('err_need_text')); return; }

        $fileId   = $photo ? end($photo)['file_id'] : $document['file_id'];
        $fileName = $document['file_name'] ?? (uniqid() . '.jpg');
        $filePath = $this->downloadFile($fileId, $fileName);

        if (!$filePath) { $this->sendMessage($chatId, BotText::get('err_upload_failed')); return; }

        if ($field->is_multiple) {
            $this->appendToMultiple($chatId, $state, $field, $filePath, $isEditing);
        } else {
            $draft = $state->draft_data ?? [];
            if ($isEditing) {
                foreach ($draft as &$item) { if ($item['field_id'] === $field->id) { $item['value'] = $filePath; break; } }
                unset($item);
            } else {
                $draft[] = ['field_id' => $field->id, 'label' => $field->label, 'type' => $field->type, 'value' => $filePath];
                $this->popField($state);
                $this->prependAlwaysChildFields($state, $field);
            }
            $state->update(['draft_data' => $draft, 'step' => $isEditing ? 'preview' : 'answering_field']);
            $this->deleteTrackedMessage($chatId, $state);
            if ($isEditing) { $this->sendMessage($chatId, BotText::get('file_edit_done')); $this->showPreview($chatId, $state); }
            else $this->askNextField($chatId, $state);
        }
    }

    private function appendToMultiple(string $chatId, BotState $state, CategoryField $field, string $value, bool $isEditing): void
    {
        $draft    = $state->draft_data ?? [];
        $found    = false;
        $count    = 1;
        foreach ($draft as &$item) {
            if ($item['field_id'] === $field->id) {
                $existing   = is_array($item['value']) ? $item['value'] : [$item['value']];
                $existing[] = $value;
                $item['value'] = $existing;
                $count = count($existing);
                $found = true;
                break;
            }
        }
        unset($item);
        if (!$found) {
            $draft[] = ['field_id' => $field->id, 'label' => $field->label, 'type' => $field->type, 'value' => [$value]];
            $count   = 1;
        }
        $state->update(['draft_data' => $draft]);
        $this->deleteTrackedMessage($chatId, $state);
        $this->sendMultipleDonePrompt($chatId, $state, $field, $count, $isEditing);
    }

    private function handleMultipleDone(string $chatId, BotState $state, bool $isEditing): void
    {
        $field = $this->currentField($state);
        if (!$field) {
            $this->deleteTrackedMessage($chatId, $state);
            if ($isEditing) { $state->update(['step' => 'preview']); $this->showPreview($chatId, $state); }
            else { $this->askNextField($chatId, $state); }
            return;
        }

        $draft = $state->draft_data ?? [];
        $found = false;
        foreach ($draft as $item) { if ($item['field_id'] === $field->id) { $found = true; break; } }

        if (!$found) { $this->sendMessage($chatId, BotText::get('err_need_at_least_one', ['type' => $this->fieldTypeName($field->type)])); return; }

        $this->deleteTrackedMessage($chatId, $state);
        if ($isEditing) { $state->update(['step' => 'preview']); $this->showPreview($chatId, $state); }
        else { $this->popField($state); $this->prependAlwaysChildFields($state, $field); $this->askNextField($chatId, $state); }
    }

    private function saveEditedAnswer(string $chatId, string $text, BotState $state): void
    {
        $field = $this->currentField($state);
        if (!$field) { $state->update(['step' => 'preview']); $this->showPreview($chatId, $state); return; }
        if ($field->type === 'photo') { $this->sendMessage($chatId, BotText::get('err_need_photo_edit')); return; }
        if ($field->type === 'date')  { $this->sendMessage($chatId, BotText::get('err_use_calendar')); return; }

        $draft = $state->draft_data ?? [];
        foreach ($draft as &$item) {
            if ($item['field_id'] === $field->id) {
                if ($field->is_multiple) {
                    $existing   = is_array($item['value']) ? $item['value'] : [];
                    $existing[] = $text;
                    $item['value'] = $existing;
                    $state->update(['draft_data' => $draft]);
                    $this->deleteTrackedMessage($chatId, $state);
                    $this->sendMultipleDonePrompt($chatId, $state, $field, count($existing), true);
                    return;
                }
                $item['value'] = $text;
                break;
            }
        }
        unset($item);
        $state->update(['draft_data' => $draft, 'step' => 'preview']);
        $this->deleteTrackedMessage($chatId, $state);
        $this->sendMessage($chatId, BotText::get('edit_done'));
        $this->showPreview($chatId, $state);
    }

    private function startEditField(string $chatId, BotState $state, int $fieldId): void
    {
        $field = CategoryField::find($fieldId);
        if (!$field) { $this->sendMessage($chatId, BotText::get('err_field_invalid')); $this->showPreview($chatId, $state); return; }

        if ($field->is_multiple) {
            $draft = $state->draft_data ?? [];
            foreach ($draft as &$item) { if ($item['field_id'] === $fieldId) { $item['value'] = []; break; } }
            unset($item);
            $state->update(['draft_data' => $draft]);
        }

        $state->update(['step' => 'editing_field', 'field_queue' => [$fieldId]]);
        $this->deleteTrackedMessage($chatId, $state);

        if ($field->type === 'option')    $this->askOptionField($chatId, $state, $field);
        elseif ($field->type === 'date')  $this->askDateField($chatId, $state, $field);
        else { $msgId = $this->sendMessage($chatId, $this->buildFieldPrompt($field)); $state->update(['last_message_id' => $msgId]); }
    }

    // ==========================================
    // Date picker (تقویم شمسی)
    // ==========================================

    /** شروع انتخاب تاریخ: گرید سال‌ها */
    private function askDateField(string $chatId, BotState $state, CategoryField $field): void
    {
        $msgId = $this->sendMessage(
            $chatId,
            $this->datePrompt($field),
            $this->withSkipButton(JalaliCalendar::yearKeyboard($field->date_range, 0), $field)
        );
        $state->update(['last_message_id' => $msgId, 'step' => $state->step === 'editing_field' ? 'editing_field' : 'answering_field']);
    }

    /**
     * ردیف دکمه‌های زیر هر سوال.
     *
     * چک‌باکس «اجباری» فرم‌ساز تا امروز در ربات خوانده نمی‌شد و همه‌ی فیلدها
     * عملاً اجباری بودند. حالا فیلدِ غیراجباری دکمه‌ی «(خالی)» می‌گیرد.
     */
    private function fieldNavRow(CategoryField $field): array
    {
        $row = [];
        if (!$field->is_required) {
            $row[] = ['text' => BotText::get('btn_skip_field'), 'callback_data' => 'field_skip'];
        }
        $row[] = ['text' => BotText::get('btn_back'), 'callback_data' => 'go_back'];
        return $row;
    }

    /** دکمه‌ی «(خالی)» را به ردیف آخر یک کیبورد آماده اضافه می‌کند (تقویم) */
    private function withSkipButton(array $keyboard, CategoryField $field): array
    {
        if ($field->is_required) return $keyboard;

        $rows = $keyboard['inline_keyboard'];
        if (!$rows) return $keyboard;

        array_unshift($rows[count($rows) - 1], [
            'text'          => BotText::get('btn_skip_field'),
            'callback_data' => 'field_skip',
        ]);
        $keyboard['inline_keyboard'] = $rows;

        return $keyboard;
    }

    /** کاربر «(خالی)» را زد: مقدار خالی ثبت می‌شود و سوال بعدی می‌آید */
    private function handleFieldSkip(string $chatId, BotState $state): void
    {
        $field = $this->currentField($state);
        if (!$field) { $this->askNextField($chatId, $state); return; }

        // دکمه‌ی کهنه‌ی یک پیام قدیمی نباید فیلد اجباری را رد کند
        if ($field->is_required) {
            $this->sendMessage($chatId, BotText::get('err_field_required'));
            return;
        }

        $this->storeAnswerAndAdvance($chatId, $state, $field, BotText::get('skipped_value'), $field->type);
    }

    /**
     * متن تقویم: انتخاب‌های تأییدشده با ✅ می‌مانند و سوال مرحله‌ی جاری زیرشان می‌آید.
     *
     *   ✅ سال انتخابی شما: ۱۴۰۵
     *   ✅ ماه انتخابی شما: شهریور
     *   📅 روز مورد نظر خود را انتخاب کنید
     */
    private function datePrompt(CategoryField $field, ?int $year = null, ?int $month = null): string
    {
        $lines = [];

        if ($year !== null) {
            $lines[] = BotText::get('date_chosen_year', ['year' => JalaliCalendar::fa($year)]);
        }
        if ($month !== null) {
            $lines[] = BotText::get('date_chosen_month', ['month' => JalaliCalendar::MONTH_NAMES[$month] ?? $month]);
        }

        // سوال مرحله‌ی جاری: هرچه هنوز انتخاب نشده
        if ($year === null)       $lines[] = BotText::get('date_pick_year');
        elseif ($month === null)  $lines[] = BotText::get('date_pick_month');
        else                      $lines[] = BotText::get('date_pick_day');

        $prompt = implode("\n", $lines) . "\n\n🔹 *{$field->label}*";
        if ($field->description) $prompt .= "\n📝 _{$field->description}_";

        return $prompt;
    }

    /**
     * همه‌ی مراحل تقویم روی همان پیام ویرایش می‌شوند تا گفت‌وگو شلوغ نشود.
     *   caly_<page> سال‌ها | calm_<y> ماه‌ها | cald_<y>_<m> روزها | calp_<y>_<m>_<d> نهایی
     */
    private function handleCalendarCallback(string $chatId, BotState $state, string $data): void
    {
        $field = $this->currentField($state);
        if (!$field || $field->type !== 'date') {
            $this->sendMessage($chatId, BotText::get('err_field_invalid'));
            return;
        }

        $range = $field->date_range;

        if (str_starts_with($data, 'caly_')) {
            $page = max(0, (int) substr($data, 5));
            $this->editCalendar($chatId, $state,
                $this->datePrompt($field),
                $this->withSkipButton(JalaliCalendar::yearKeyboard($range, $page), $field));
            return;
        }

        if (str_starts_with($data, 'calm_')) {
            $year = (int) substr($data, 5);
            $this->editCalendar($chatId, $state,
                $this->datePrompt($field, $year),
                $this->withSkipButton(JalaliCalendar::monthKeyboard($year, $range), $field));
            return;
        }

        if (str_starts_with($data, 'cald_')) {
            [$year, $month] = array_map('intval', explode('_', substr($data, 5)));
            $this->editCalendar($chatId, $state,
                $this->datePrompt($field, $year, $month),
                $this->withSkipButton(JalaliCalendar::dayKeyboard($year, $month, $range), $field));
            return;
        }

        if (str_starts_with($data, 'calp_')) {
            [$year, $month, $day] = array_map('intval', explode('_', substr($data, 5)));

            // دکمه‌های کهنه (پیام قدیمی) می‌توانند تاریخ نامعتبر بفرستند
            if (!JalaliCalendar::isValid($year, $month, $day) || !JalaliCalendar::inRange($year, $month, $day, $range)) {
                $this->sendMessage($chatId, BotText::get('err_invalid_date'));
                return;
            }

            $this->storeAnswerAndAdvance($chatId, $state, $field, JalaliCalendar::formatLong($year, $month, $day), 'date');
        }
    }

    /** یک پاسخ تک‌مقداری را در draft می‌نشاند و به سوال بعدی می‌رود */
    private function storeAnswerAndAdvance(string $chatId, BotState $state, CategoryField $field, string $value, string $type): void
    {
        $isEditing = $state->step === 'editing_field';
        $draft     = $state->draft_data ?? [];

        if ($isEditing) {
            foreach ($draft as &$item) {
                if ($item['field_id'] === $field->id) { $item['value'] = $value; break; }
            }
            unset($item);
            $state->update(['draft_data' => $draft, 'step' => 'preview']);
            $this->deleteTrackedMessage($chatId, $state);
            $this->sendMessage($chatId, BotText::get('edit_done'));
            $this->showPreview($chatId, $state);
            return;
        }

        $draft[] = ['field_id' => $field->id, 'label' => $field->label, 'type' => $type, 'value' => $value];
        $this->popField($state);
        $this->prependAlwaysChildFields($state, $field);
        $state->update(['draft_data' => $draft]);
        $this->deleteTrackedMessage($chatId, $state);
        $this->askNextField($chatId, $state);
    }

    /** متن و دکمه‌های همان پیام را عوض می‌کند تا گفت‌وگو پر از پیام تکراری نشود */
    private function editCalendar(string $chatId, BotState $state, string $text, array $replyMarkup): void
    {
        if (!$state->last_message_id) return;

        Http::post($this->apiUrl . 'editMessageText', [
            'chat_id'      => $chatId,
            'message_id'   => $state->last_message_id,
            'text'         => $text,
            'parse_mode'   => 'Markdown',
            'reply_markup' => json_encode($replyMarkup),
        ]);
    }

    // ==========================================
    // Preview & Save
    // ==========================================

    private function showPreview(string $chatId, BotState $state): void
    {
        $category       = Category::find($state->category_id);
        $draft          = $state->draft_data ?? [];
        $formattedMonth = $this->formatJalaliMonthName($state->jalali_month ?? '');

        $msg = BotText::get('preview_title') . "\n"
             . BotText::get('preview_month_label') . ": $formattedMonth\n"
             . BotText::get('preview_category_label') . ": {$category->name}\n\n";
        foreach ($draft as $item) {
            $val = $item['value'];
            if ($item['type'] === 'branch') {
                $msg .= BotText::get('preview_branch_label') . " {$val}\n\n";
                continue;
            }
            $display = is_array($val)
                ? ($item['type'] === 'photo' ? BotText::get('photo_uploaded_count', ['count' => count($val)]) : '• ' . implode("\n• ", $val))
                : ($item['type'] === 'photo' ? BotText::get('photo_uploaded_single') : $val);
            $msg .= "▫️ *{$item['label']}:*\n{$display}\n\n";
        }

        $keyboard = ['inline_keyboard' => [
            [['text' => BotText::get('btn_confirm'),     'callback_data' => 'confirm_report']],
            [['text' => BotText::get('btn_request_edit'), 'callback_data' => 'request_edit']],
        ]];

        $msgId = $this->sendMessage($chatId, $msg, $keyboard);
        $state->update(['last_message_id' => $msgId]);
    }

    private function showEditOptions(string $chatId, BotState $state): void
    {
        $draft          = $state->draft_data ?? [];
        $editableItems  = array_values(array_filter($draft, fn($item) => ($item['type'] ?? '') !== 'branch'));
        $inlineKeyboard = array_map(fn($item) => [['text' => BotText::get('btn_edit_item', ['label' => $item['label']]), 'callback_data' => "edit_field_{$item['field_id']}"]], $editableItems);
        $msgId = $this->sendMessage($chatId, BotText::get('ask_which_edit'), ['inline_keyboard' => $inlineKeyboard]);
        $state->update(['last_message_id' => $msgId]);
    }

    private function saveFinalReport(string $chatId, BotState $state): void
    {
        $this->deleteTrackedMessage($chatId, $state);
        Report::create([
            'representative_id' => $state->representative_id,
            'department_id'     => $state->department_id,
            'category_id'       => $state->category_id,
            'jalali_month'      => $state->jalali_month ?? \Morilog\Jalali\Jalalian::now()->format('Y-m'),
            'data'              => $state->draft_data,
        ]);
        $state->update(['step' => 'idle', 'draft_data' => [], 'field_queue' => []]);
        $this->sendMessage($chatId, BotText::get('report_saved'));
        $this->showMainMenu($chatId);
    }

    // ==========================================
    // Helpers
    // ==========================================

    private function buildFieldPrompt(CategoryField $field): string
    {
        $msg = BotText::get('field_prompt_header') . "\n\n🔹 *{$field->label}*";
        if ($field->type === 'photo')
            $msg .= "\n\n" . BotText::get($field->is_multiple ? 'hint_photo_multiple' : 'hint_photo_single');
        elseif ($field->type === 'link')
            $msg .= "\n\n" . BotText::get('hint_link');
        if (!empty($field->description))
            $msg .= "\n📝 _{$field->description}_";
        return $msg;
    }

    private function sendMultipleDonePrompt(string $chatId, BotState $state, CategoryField $field, int $count, bool $isEditing): void
    {
        $typeLabel    = $this->fieldTypeName($field->type);
        $doneCallback = $isEditing ? 'field_multiple_done_edit' : 'field_multiple_done';
        $text         = BotText::get('multiple_progress', ['count' => $count, 'type' => $typeLabel]);
        $keyboard     = ['inline_keyboard' => [[['text' => BotText::get('btn_multiple_done'), 'callback_data' => $doneCallback]]]];
        $msgId        = $this->sendMessage($chatId, $text, $keyboard);
        BotState::where('chat_id', $chatId)->update(['last_message_id' => $msgId]);
    }

    /** متغیرهای در دسترس متن‌های مربوط به نماینده */
    private function repVars($rep): array
    {
        return [
            'name'     => $rep->first_name,
            'family'   => $rep->last_name,
            'fullname' => trim($rep->first_name . ' ' . $rep->last_name),
            'province' => $rep->province->name ?? '',
        ];
    }

    private function fieldTypeName(string $type): string
    {
        return match ($type) {
            'photo' => BotText::get('type_photo'),
            'link'  => BotText::get('type_link'),
            'date'  => BotText::get('type_date'),
            default => BotText::get('type_item'),
        };
    }

    private function sendMessage(string $chatId, string $text, $replyMarkup = null): ?int
    {
        $data = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'Markdown'];
        if ($replyMarkup) $data['reply_markup'] = json_encode($replyMarkup);
        $response = Http::post($this->apiUrl . 'sendMessage', $data);
        return $response->json()['result']['message_id'] ?? null;
    }

    private function deleteMessage(string $chatId, int $messageId): void
    {
        Http::post($this->apiUrl . 'deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
    }

    private function deleteTrackedMessage(string $chatId, BotState $state): void
    {
        if ($state->last_message_id) { $this->deleteMessage($chatId, $state->last_message_id); $state->last_message_id = null; $state->save(); }
    }

    private function downloadFile(string $fileId, string $originalName): ?string
    {
        $response = Http::post($this->apiUrl . 'getFile', ['file_id' => $fileId]);
        $fileData = $response->json();
        if (isset($fileData['result']['file_path'])) {
            $remotePath  = $fileData['result']['file_path'];
            $fileUrl     = "https://tapi.bale.ai/file/bot{$this->token}/" . $remotePath;
            $fileContent = Http::get($fileUrl)->body();
            $extension   = pathinfo($remotePath, PATHINFO_EXTENSION) ?: pathinfo($originalName, PATHINFO_EXTENSION) ?: 'bin';
            // فایل‌ها روی دیسک public سرو می‌شوند؛ مسیر هر سازمان جداست و نام فایل
            // به‌جای uniqid (که زمان‌محور و قابل حدس است) تصادفی ساخته می‌شود.
            $fileName    = 'uploads/' . TenantContext::id() . '/' . Str::random(32) . '.' . $extension;
            Storage::disk('public')->put($fileName, $fileContent);
            return $fileName;
        }
        return null;
    }

    private function formatJalaliMonthName(string $monthString): string
    {
        $parts = explode('-', $monthString);
        if (count($parts) !== 2) return $monthString;
        $monthsName = [1=>'فروردین',2=>'اردیبهشت',3=>'خرداد',4=>'تیر',5=>'مرداد',6=>'شهریور',7=>'مهر',8=>'آبان',9=>'آذر',10=>'دی',11=>'بهمن',12=>'اسفند'];
        return ($monthsName[(int)$parts[1]] ?? '') . ' ' . $parts[0];
    }
}
