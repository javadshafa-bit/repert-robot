<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BotState;
use App\Models\Category;
use App\Models\Department;
use App\Models\Report;
use App\Models\Representative;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Morilog\Jalali\Jalalian;

class BotController extends Controller
{
    private $token;
    private $apiUrl;

    public function __construct()
    {
        $this->token = Setting::get('bot_token');
        $this->apiUrl = "https://tapi.bale.ai/bot{$this->token}/";
    }

    public function handle(Request $request)
    {
        $update = $request->all();
        Log::info(json_encode($update, JSON_UNESCAPED_UNICODE));

        if (isset($update['message'])) {
            $this->processMessage($update['message']);
        } elseif (isset($update['callback_query'])) {
            $this->processCallback($update['callback_query']);
        }

        return response('OK', 200);
    }

    private function processMessage($message)
    {
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? null;
        $photo = $message['photo'] ?? null;
        $document = $message['document'] ?? null;

        $state = BotState::firstOrCreate(['chat_id' => $chatId]);

        if ($text === '/start') {
            return $this->handleStart($chatId, $state);
        }

        if (isset($message['contact'])) {
            return $this->handleContact($chatId, $message['contact']['phone_number'], $state);
        }

        if (!$state->representative_id) {
            return $this->sendMessage($chatId, Setting::get('error_message', 'شما مجاز به استفاده نیستید.'));
        }

        if ($text === '📝 ارسال گزارش جدید') {
            if ($state->step !== 'idle') {
                $this->sendMessage($chatId, "⚠️ شما در حال ارسال یک گزارش هستید. لطفاً ابتدا مراحل جاری را تکمیل کنید.");
                return;
            }
            return $this->startReportFlow($chatId, $state);
        }

        if ($state->step === 'answering_field') {
            if ($text) {
                return $this->saveAnswerAndAskNext($chatId, $text, $state);
            } elseif ($photo || $document) {
                return $this->handleFileUpload($chatId, $photo, $document, $state);
            }
        }

        if ($state->step === 'editing_field') {
            if ($text) {
                return $this->saveEditedAnswer($chatId, $text, $state);
            } elseif ($photo || $document) {
                return $this->handleFileUpload($chatId, $photo, $document, $state, true);
            }
        }

        $this->showMainMenu($chatId);
    }

    private function processCallback($callbackQuery)
    {
        $chatId = $callbackQuery['message']['chat']['id'];
        $data = $callbackQuery['data'];
        $state = BotState::where('chat_id', $chatId)->first();

        if (!$state || !$state->representative_id) return;

        if (str_starts_with($data, 'month_')) {
            $month = str_replace('month_', '', $data);
            $state->update(['jalali_month' => $month, 'step' => 'selecting_department']);
            $this->deleteTrackedMessage($chatId, $state);
            $this->askDepartment($chatId, $state);

        } elseif (str_starts_with($data, 'department_')) {
            $departmentId = str_replace('department_', '', $data);
            $state->update(['department_id' => $departmentId, 'step' => 'selecting_category']);
            $this->deleteTrackedMessage($chatId, $state);
            $this->askCategory($chatId, $state);

        } elseif (str_starts_with($data, 'category_')) {
            $categoryId = str_replace('category_', '', $data);
            $state->update([
                'category_id' => $categoryId,
                'current_field_index' => 0,
                'draft_data' => [],
                'step' => 'answering_field',
            ]);
            $this->deleteTrackedMessage($chatId, $state);
            $this->askNextField($chatId, $state);

        } elseif ($data === 'field_multiple_done') {
            $this->handleMultipleDone($chatId, $state, false);

        } elseif ($data === 'field_multiple_done_edit') {
            $this->handleMultipleDone($chatId, $state, true);

        } elseif ($data === 'confirm_report') {
            $this->saveFinalReport($chatId, $state);

        } elseif ($data === 'request_edit') {
            $this->deleteTrackedMessage($chatId, $state);
            $this->showEditOptions($chatId, $state);

        } elseif (str_starts_with($data, 'edit_field_')) {
            $fieldIndex = (int)str_replace('edit_field_', '', $data);
            $state->update(['step' => 'editing_field', 'current_field_index' => $fieldIndex]);
            $category = Category::with('fields')->find($state->category_id);
            $field = $category->fields[$fieldIndex];

            $this->deleteTrackedMessage($chatId, $state);

            // برای فیلد چندتایی در حالت ویرایش، مقدار قبلی پاک می‌شود
            if ($field->is_multiple) {
                $draft = $state->draft_data ?? [];
                $draft[$field->id] = [];
                $state->update(['draft_data' => $draft]);
            }

            $msgId = $this->sendMessage($chatId, $this->buildFieldPrompt($field, $field->is_multiple));
            $state->update(['last_message_id' => $msgId]);
        }
    }

    // ==========================================

    private function handleStart($chatId, $state)
    {
        if ($state->representative_id) {
            $rep = Representative::with('province')->find($state->representative_id);
            $this->sendMessage($chatId, "سلام {$rep->first_name} عزیز از استان {$rep->province->name}! خوش آمدید.");
            return $this->showMainMenu($chatId);
        }

        $welcome = Setting::get('welcome_message', 'خوش آمدید. لطفاً شماره خود را ارسال کنید.');
        $keyboard = [
            'keyboard' => [[['text' => '📱 ارسال شماره تماس (جهت احراز هویت)', 'request_contact' => true]]],
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ];

        $this->sendMessage($chatId, $welcome, $keyboard);
        $state->update(['step' => 'waiting_for_contact']);
    }

    private function handleContact($chatId, $phoneNumber, $state)
    {
        if (str_starts_with($phoneNumber, '+98')) {
            $phoneNumber = '0' . substr($phoneNumber, 3);
        } elseif (str_starts_with($phoneNumber, '98')) {
            $phoneNumber = '0' . substr($phoneNumber, 2);
        }

        $rep = Representative::with('province')->where('phone_number', $phoneNumber)->first();

        if ($rep) {
            $rep->update(['chat_id' => $chatId, 'is_connected' => true]);
            $state->update(['representative_id' => $rep->id, 'step' => 'idle']);
            $this->sendMessage($chatId, "احراز هویت موفق. سلام {$rep->first_name} عزیز از استان {$rep->province->name}!");
            $this->showMainMenu($chatId);
        } else {
            $this->sendMessage($chatId, Setting::get('error_message', 'شماره شما در سیستم ثبت نشده است.'));
        }
    }

    private function showMainMenu($chatId)
    {
        BotState::where('chat_id', $chatId)->update(['step' => 'idle', 'last_message_id' => null]);
        $keyboard = [
            'keyboard' => [[['text' => '📝 ارسال گزارش جدید']]],
            'resize_keyboard' => true,
        ];
        $this->sendMessage($chatId, "لطفاً یک گزینه را انتخاب کنید:", $keyboard);
    }

    private function startReportFlow($chatId, $state)
    {
        $state->update(['step' => 'selecting_month']);

        // حذف کیبورد ریپلای
        $this->sendMessage($chatId, "🗓 در حال آماده‌سازی فرم گزارش...", ['remove_keyboard' => true]);

        $now = Jalalian::now();
        $months = [$now->format('Y-m'), $now->subMonths(1)->format('Y-m'), $now->subMonths(2)->format('Y-m')];

        $inlineKeyboard = [];
        foreach ($months as $m) {
            $inlineKeyboard[] = [['text' => 'گزارش ' . $this->formatJalaliMonthName($m), 'callback_data' => "month_$m"]];
        }

        $msgId = $this->sendMessage($chatId, "ابتدا ماه گزارش را انتخاب کنید:", ['inline_keyboard' => $inlineKeyboard]);
        $state->update(['last_message_id' => $msgId]);
    }

    private function askDepartment($chatId, $state)
    {
        $departments = Department::where('is_active', true)->orderBy('sort_order')->get();
        if ($departments->isEmpty()) {
            $this->sendMessage($chatId, "هیچ دپارتمان فعالی وجود ندارد!");
            return;
        }
        $inlineKeyboard = [];
        foreach ($departments as $dep) {
            $inlineKeyboard[] = [['text' => $dep->name, 'callback_data' => "department_{$dep->id}"]];
        }
        $msgId = $this->sendMessage($chatId, "دپارتمان مربوطه را انتخاب کنید:", ['inline_keyboard' => $inlineKeyboard]);
        $state->update(['last_message_id' => $msgId]);
    }

    private function askCategory($chatId, $state)
    {
        $categories = Category::where('is_active', true)->orderBy('sort_order')->get();
        if ($categories->isEmpty()) {
            $this->sendMessage($chatId, "هیچ دسته‌بندی فعالی وجود ندارد!");
            return;
        }
        $inlineKeyboard = [];
        foreach ($categories as $cat) {
            $inlineKeyboard[] = [['text' => $cat->name, 'callback_data' => "category_{$cat->id}"]];
        }
        $msgId = $this->sendMessage($chatId, "نوع گزارش (دسته‌بندی) را انتخاب کنید:", ['inline_keyboard' => $inlineKeyboard]);
        $state->update(['last_message_id' => $msgId]);
    }

    private function askNextField($chatId, $state)
    {
        $category = Category::with('fields')->find($state->category_id);
        $fields = $category->fields;

        if ($state->current_field_index < $fields->count()) {
            $field = $fields[$state->current_field_index];
            $msgId = $this->sendMessage($chatId, $this->buildFieldPrompt($field));
            $state->update(['last_message_id' => $msgId]);
        } else {
            $state->update(['step' => 'preview']);
            $this->showPreview($chatId, $state);
        }
    }

    private function saveAnswerAndAskNext($chatId, $text, $state)
    {
        $category = Category::with('fields')->find($state->category_id);
        $field = $category->fields[$state->current_field_index];

        // اعتبارسنجی نوع
        if (in_array($field->type, ['photo', 'document'])) {
            $this->sendMessage($chatId, "⚠️ برای این فیلد باید " . ($field->type === 'photo' ? 'عکس' : 'فایل') . " ارسال کنید.");
            return;
        }

        // اعتبارسنجی لینک
        if ($field->type === 'link') {
            $pattern = '/^(?:https?:\/\/)?(?:www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{2,6}\b(?:[-a-zA-Z0-9()@:%_\+.~#?&\/\/=]*)$/i';
            if (!preg_match($pattern, $text)) {
                $this->sendMessage($chatId, "⚠️ لینک وارد شده معتبر نیست.\nلطفاً لینک را با فرمت صحیح ارسال کنید.\n\nمثال: `https://example.com`");
                return;
            }
        }

        $draft = $state->draft_data ?? [];

        if ($field->is_multiple) {
            $count = 0;
            $prevMsgId = null;
            DB::transaction(function () use ($chatId, $text, $field, &$count, &$prevMsgId) {
                $fresh = BotState::where('chat_id', $chatId)->lockForUpdate()->first();
                $draft = $fresh->draft_data ?? [];
                $existing = isset($draft[$field->id]) && is_array($draft[$field->id]) ? $draft[$field->id] : [];
                $existing[] = $text;
                $draft[$field->id] = $existing;
                $count = count($existing);
                $prevMsgId = $fresh->last_message_id;
                $fresh->update(['draft_data' => $draft, 'last_message_id' => null]);
            });
            if ($prevMsgId) $this->deleteMessage($chatId, $prevMsgId);
            $this->sendMultipleDonePrompt($chatId, $state, $field, $count, false);
        } else {
            $draft[$field->id] = $text;
            $state->update(['draft_data' => $draft, 'current_field_index' => $state->current_field_index + 1]);
            $this->deleteTrackedMessage($chatId, $state);
            $this->askNextField($chatId, $state);
        }
    }

    private function handleFileUpload($chatId, $photo, $document, $state, $isEditing = false)
    {
        $category = Category::with('fields')->find($state->category_id);
        $field = $category->fields[$state->current_field_index];

        if (in_array($field->type, ['text', 'link'])) {
            $this->sendMessage($chatId, "⚠️ این فیلد نیاز به پاسخ متنی دارد. لطفاً متن ارسال کنید.");
            return;
        }

        $fileId = $photo ? end($photo)['file_id'] : $document['file_id'];
        $fileName = $document['file_name'] ?? (uniqid() . '.jpg');
        $filePath = $this->downloadFile($fileId, $fileName);

        if (!$filePath) {
            $this->sendMessage($chatId, "⚠️ خطایی در آپلود فایل رخ داد. لطفاً دوباره تلاش کنید.");
            return;
        }

        if ($field->is_multiple) {
            $count = 0;
            $prevMsgId = null;
            DB::transaction(function () use ($chatId, $filePath, $field, $isEditing, &$count, &$prevMsgId) {
                $fresh = BotState::where('chat_id', $chatId)->lockForUpdate()->first();
                $draft = $fresh->draft_data ?? [];
                $existing = isset($draft[$field->id]) && is_array($draft[$field->id]) ? $draft[$field->id] : [];
                $existing[] = $filePath;
                $draft[$field->id] = $existing;
                $count = count($existing);
                $prevMsgId = $fresh->last_message_id;
                $fresh->update(['draft_data' => $draft, 'last_message_id' => null]);
            });
            if ($prevMsgId) $this->deleteMessage($chatId, $prevMsgId);
            $this->sendMultipleDonePrompt($chatId, $state, $field, $count, $isEditing);
        } else {
            $draft = $state->draft_data ?? [];
            $draft[$field->id] = $filePath;
            $state->draft_data = $draft;
            $this->deleteTrackedMessage($chatId, $state);

            if ($isEditing) {
                $state->step = 'preview';
                $state->save();
                $this->sendMessage($chatId, "✅ فایل با موفقیت ویرایش و جایگزین شد.");
                $this->showPreview($chatId, $state);
            } else {
                $state->current_field_index += 1;
                $state->save();
                $this->askNextField($chatId, $state);
            }
        }
    }

    private function handleMultipleDone($chatId, $state, $isEditing)
    {
        $category = Category::with('fields')->find($state->category_id);
        $field = $category->fields[$state->current_field_index];
        $draft = $state->draft_data ?? [];

        // بررسی ارسال حداقل یک آیتم
        if (empty($draft[$field->id])) {
            $this->sendMessage($chatId, "⚠️ لطفاً حداقل یک " . $this->fieldTypeName($field->type) . " ارسال کنید.");
            return;
        }

        $this->deleteTrackedMessage($chatId, $state);

        if ($isEditing) {
            $state->update(['step' => 'preview']);
            $this->showPreview($chatId, $state);
        } else {
            $state->update(['current_field_index' => $state->current_field_index + 1]);
            $this->askNextField($chatId, $state);
        }
    }

    private function showPreview($chatId, $state)
    {
        $category = Category::with('fields')->find($state->category_id);
        $draft = $state->draft_data ?? [];
        $formattedMonth = $this->formatJalaliMonthName($state->jalali_month);

        $msg = "📄 *پیش‌نمایش گزارش شما*\nماه: $formattedMonth\nدسته: {$category->name}\n\n";

        foreach ($category->fields as $field) {
            $val = $draft[$field->id] ?? null;

            if ($field->is_multiple && is_array($val)) {
                $count = count($val);
                if ($field->type === 'photo') {
                    $display = "$count عکس آپلود شد";
                } elseif ($field->type === 'document') {
                    $display = "$count فایل آپلود شد";
                } else {
                    $display = implode("\n• ", $val);
                    $display = "• $display";
                }
            } elseif ($field->type === 'photo') {
                $display = $val ? '[عکس آپلود شد]' : '-';
            } elseif ($field->type === 'document') {
                $display = $val ? '[فایل آپلود شد]' : '-';
            } else {
                $display = $val ?? '-';
            }

            $msg .= "▫️ *{$field->label}:*\n{$display}\n\n";
        }

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '✅ تایید و ارسال نهایی', 'callback_data' => 'confirm_report']],
                [['text' => '✏️ ویرایش پاسخ‌ها', 'callback_data' => 'request_edit']],
            ],
        ];

        $msgId = $this->sendMessage($chatId, $msg, $keyboard);
        $state->update(['last_message_id' => $msgId]);
    }

    private function showEditOptions($chatId, $state)
    {
        $category = Category::with('fields')->find($state->category_id);
        $inlineKeyboard = [];

        foreach ($category->fields as $index => $field) {
            $inlineKeyboard[] = [['text' => 'ویرایش: ' . $field->label, 'callback_data' => "edit_field_{$index}"]];
        }

        $msgId = $this->sendMessage($chatId, "کدام بخش را می‌خواهید ویرایش کنید؟", ['inline_keyboard' => $inlineKeyboard]);
        $state->update(['last_message_id' => $msgId]);
    }

    private function saveEditedAnswer($chatId, $text, $state)
    {
        $category = Category::with('fields')->find($state->category_id);
        $field = $category->fields[$state->current_field_index];

        if (in_array($field->type, ['photo', 'document'])) {
            $this->sendMessage($chatId, "⚠️ برای ویرایش این فیلد باید " . ($field->type === 'photo' ? 'عکس' : 'فایل') . " ارسال کنید.");
            return;
        }

        if ($field->type === 'link') {
            if (!preg_match('/^https?:\/\/.+/i', $text)) {
                $this->sendMessage($chatId, "⚠️ لینک وارد شده معتبر نیست.\nلطفاً لینک را با فرمت صحیح ارسال کنید.\n\nمثال: `https://example.com`");
                return;
            }
        }

        $draft = $state->draft_data;

        if ($field->is_multiple) {
            $count = 0;
            $prevMsgId = null;
            DB::transaction(function () use ($chatId, $text, $field, &$count, &$prevMsgId) {
                $fresh = BotState::where('chat_id', $chatId)->lockForUpdate()->first();
                $draft = $fresh->draft_data ?? [];
                $existing = isset($draft[$field->id]) && is_array($draft[$field->id]) ? $draft[$field->id] : [];
                $existing[] = $text;
                $draft[$field->id] = $existing;
                $count = count($existing);
                $prevMsgId = $fresh->last_message_id;
                $fresh->update(['draft_data' => $draft, 'last_message_id' => null]);
            });
            if ($prevMsgId) $this->deleteMessage($chatId, $prevMsgId);
            $this->sendMultipleDonePrompt($chatId, $state, $field, $count, true);
        } else {
            $draft[$field->id] = $text;
            $state->update(['draft_data' => $draft, 'step' => 'preview']);
            $this->deleteTrackedMessage($chatId, $state);
            $this->sendMessage($chatId, "✅ ویرایش انجام شد.");
            $this->showPreview($chatId, $state);
        }
    }

    private function saveFinalReport($chatId, $state)
    {
        $this->deleteTrackedMessage($chatId, $state);

        Report::create([
            'representative_id' => $state->representative_id,
            'department_id' => $state->department_id,
            'category_id' => $state->category_id,
            'jalali_month' => $state->jalali_month,
            'data' => $state->draft_data,
        ]);

        $this->sendMessage($chatId, "🎉 گزارش شما با موفقیت ثبت شد!");
        $this->showMainMenu($chatId);
    }

    // ==========================================
    // Helper Methods
    // ==========================================

    private function buildFieldPrompt($field, $isMultiple = null): string
    {
        $isMultiple = $isMultiple ?? $field->is_multiple;

        $msg = "لطفاً پاسخ دهید:\n\n🔹 *" . $field->label . "*";

        if ($field->type === 'photo') {
            $msg .= $isMultiple
                ? "\n\n(عکس‌ها را یکی یکی ارسال کنید. وقتی تمام شد دکمه پایان را بزنید)"
                : "\n\n(لطفاً *عکس* مربوطه را ارسال کنید)";
        } elseif ($field->type === 'document') {
            $msg .= $isMultiple
                ? "\n\n(فایل‌ها را یکی یکی ارسال کنید. وقتی تمام شد دکمه پایان را بزنید)"
                : "\n\n(لطفاً *فایل* مربوطه را ارسال کنید)";
        } elseif ($field->type === 'link') {
            $msg .= "\n\n(لینک را با فرمت `https://...` ارسال کنید)";
        }

        if (!empty($field->description)) {
            $msg .= "\n📝 _" . $field->description . "_";
        }

        return $msg;
    }

    private function sendMultipleDonePrompt($chatId, $state, $field, int $count, bool $isEditing): void
    {
        $typeLabel = $this->fieldTypeName($field->type);
        $doneCallback = $isEditing ? 'field_multiple_done_edit' : 'field_multiple_done';

        $text = "✅ تاکنون *{$count} {$typeLabel}* دریافت شد.\n\nمی‌توانید {$typeLabel} دیگری ارسال کنید یا دکمه پایان را بزنید.";

        $keyboard = [
            'inline_keyboard' => [[
                ['text' => '✅ پایان ارسال این بخش — مرحله بعدی', 'callback_data' => $doneCallback]
            ]]
        ];

        $msgId = $this->sendMessage($chatId, $text, $keyboard);
        // last_message_id را مستقیم در DB آپدیت می‌کنیم تا با قفل transaction تداخل نداشته باشد
        BotState::where('chat_id', $chatId)->update(['last_message_id' => $msgId]);
    }

    private function fieldTypeName(string $type): string
    {
        return match ($type) {
            'photo' => 'عکس',
            'document' => 'فایل',
            'link' => 'لینک',
            default => 'آیتم',
        };
    }

    private function sendMessage($chatId, $text, $replyMarkup = null): ?int
    {
        $data = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'Markdown'];
        if ($replyMarkup) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }
        $response = Http::post($this->apiUrl . 'sendMessage', $data);
        return $response->json()['result']['message_id'] ?? null;
    }

    private function deleteMessage($chatId, $messageId): void
    {
        if (!$messageId) return;
        Http::post($this->apiUrl . 'deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    private function deleteTrackedMessage($chatId, $state): void
    {
        if ($state->last_message_id) {
            $this->deleteMessage($chatId, $state->last_message_id);
            $state->last_message_id = null;
            $state->save();
        }
    }

    private function downloadFile($fileId, $originalName)
    {
        $response = Http::post($this->apiUrl . 'getFile', ['file_id' => $fileId]);
        $fileData = $response->json();

        if (isset($fileData['result']['file_path'])) {
            $remotePath = $fileData['result']['file_path'];
            $fileUrl = "https://tapi.bale.ai/file/bot{$this->token}/" . $remotePath;
            $fileContent = Http::get($fileUrl)->body();
            // پسوند را از مسیر واقعی فایل روی سرور بله می‌گیریم
            $extension = pathinfo($remotePath, PATHINFO_EXTENSION)
                ?: pathinfo($originalName, PATHINFO_EXTENSION)
                    ?: 'bin';
            $fileName = 'uploads/' . uniqid() . '.' . $extension;
            Storage::disk('public')->put($fileName, $fileContent);
            return $fileName;
        }
        return null;
    }

    private function formatJalaliMonthName($monthString): string
    {
        $parts = explode('-', $monthString);
        if (count($parts) != 2) return $monthString;
        $year = $parts[0];
        $month = (int)$parts[1];
        $monthsName = [1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'];
        return ($monthsName[$month] ?? '') . ' ' . $year;
    }
}
