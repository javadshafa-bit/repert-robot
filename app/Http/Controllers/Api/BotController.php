<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BotState;
use App\Models\Category;
use App\Models\CategoryField;
use App\Models\Department;
use App\Models\FieldOption;
use App\Models\Report;
use App\Models\Representative;
use App\Models\Setting;
use Illuminate\Http\Request;
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
        $this->token  = Setting::get('bot_token');
        $this->apiUrl = "https://tapi.bale.ai/bot{$this->token}/";
    }

    public function handle(Request $request)
    {
        $update = $request->all();
        Log::info(json_encode($update, JSON_UNESCAPED_UNICODE));

        if (isset($update['message']))          $this->processMessage($update['message']);
        elseif (isset($update['callback_query'])) $this->processCallback($update['callback_query']);

        return response('OK', 200);
    }

    private function processMessage($message)
    {
        $chatId   = $message['chat']['id'];
        $text     = $message['text'] ?? null;
        $photo    = $message['photo'] ?? null;
        $document = $message['document'] ?? null;

        $state = BotState::firstOrCreate(['chat_id' => $chatId]);

        if ($text === '/start') return $this->handleStart($chatId, $state);
        if (isset($message['contact'])) return $this->handleContact($chatId, $message['contact']['phone_number'], $state);
        if (!$state->representative_id) return $this->sendMessage($chatId, Setting::get('error_message', 'شما مجاز به استفاده نیستید.'));

        if (in_array($state->step, ['answering_field', 'editing_field'])) {
            $isEditing = $state->step === 'editing_field';
            if ($photo || $document) return $this->handleFileUpload($chatId, $photo, $document, $state, $isEditing);
            if ($text) return $isEditing ? $this->saveEditedAnswer($chatId, $text, $state) : $this->saveAnswerAndContinue($chatId, $text, $state);
        }

        $this->showMainMenu($chatId);
    }

    private function processCallback($callbackQuery)
    {
        $chatId = $callbackQuery['message']['chat']['id'];
        $data   = $callbackQuery['data'];
        $state  = BotState::where('chat_id', $chatId)->first();
        if (!$state || !$state->representative_id) return;

        if ($data === 'main_start_report') {
            if ($state->step !== 'idle') { $this->sendMessage($chatId, "⚠️ ابتدا گزارش جاری را تکمیل کنید."); return; }
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

        } elseif (str_starts_with($data, 'opt_')) {
            $this->handleOptionSelected($chatId, $state, (int) str_replace('opt_', '', $data));

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
            $this->startEditField($chatId, $state, (int) str_replace('edit_field_', '', $data));
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
        // ساختن صف از فیلدهای سطح اول
        $category   = Category::with(['fields' => fn($q) => $q->whereNull('parent_option_id')->orderBy('sort_order')])->find($state->category_id);
        $fieldQueue = $category->fields->pluck('id')->toArray();
        $state->update(['step' => 'answering_field', 'draft_data' => [], 'field_queue' => $fieldQueue]);
        $this->askNextField($chatId, $state);
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
        $state->update(['field_queue' => array_merge($childIds, $state->field_queue ?? [])]);
    }

    // ==========================================
    // Ask steps
    // ==========================================

    private function handleStart(string $chatId, BotState $state): void
    {
        if ($state->representative_id) {
            $rep = Representative::with('province')->find($state->representative_id);
            $this->sendMessage($chatId, "سلام {$rep->first_name} عزیز از استان {$rep->province->name}!");
            $this->showMainMenu($chatId);
            return;
        }
        $welcome  = Setting::get('welcome_message', 'خوش آمدید. لطفاً شماره خود را ارسال کنید.');
        $keyboard = ['keyboard' => [[['text' => '📱 ارسال شماره تماس (جهت احراز هویت)', 'request_contact' => true]]], 'resize_keyboard' => true, 'one_time_keyboard' => true];
        $this->sendMessage($chatId, $welcome, $keyboard);
        $state->update(['step' => 'waiting_for_contact']);
    }

    private function handleContact(string $chatId, string $phoneNumber, BotState $state): void
    {
        if (str_starts_with($phoneNumber, '+98'))    $phoneNumber = '0' . substr($phoneNumber, 3);
        elseif (str_starts_with($phoneNumber, '98')) $phoneNumber = '0' . substr($phoneNumber, 2);

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

    private function showMainMenu(string $chatId): void
    {
        $keyboard = ['inline_keyboard' => [[['text' => '📝 ارسال گزارش جدید', 'callback_data' => 'main_start_report']]]];
        $msgId    = $this->sendMessage($chatId, "لطفاً یک گزینه را انتخاب کنید:", $keyboard);
        BotState::where('chat_id', $chatId)->update(['step' => 'idle', 'last_message_id' => $msgId]);
    }

    private function askMonth(string $chatId, BotState $state): void
    {
        $now    = Jalalian::now();
        $months = [$now->format('Y-m'), $now->subMonths(1)->format('Y-m'), $now->subMonths(2)->format('Y-m')];
        $inlineKeyboard = array_map(fn($m) => [['text' => 'گزارش ' . $this->formatJalaliMonthName($m), 'callback_data' => "month_$m"]], $months);
        $msgId = $this->sendMessage($chatId, "ماه گزارش را انتخاب کنید:", ['inline_keyboard' => $inlineKeyboard]);
        $state->update(['last_message_id' => $msgId]);
    }

    private function askDepartment(string $chatId, BotState $state): void
    {
        $departments = Department::where('is_active', true)->orderBy('sort_order')->get();
        if ($departments->isEmpty()) { $this->sendMessage($chatId, "هیچ دپارتمان فعالی وجود ندارد!"); return; }
        $inlineKeyboard = $departments->map(fn($d) => [['text' => $d->name, 'callback_data' => "department_{$d->id}"]])->toArray();
        $msgId = $this->sendMessage($chatId, "دپارتمان مربوطه را انتخاب کنید:", ['inline_keyboard' => $inlineKeyboard]);
        $state->update(['last_message_id' => $msgId]);
    }

    private function askCategory(string $chatId, BotState $state): void
    {
        $categories = Category::where('is_active', true)->orderBy('sort_order')->get();
        if ($categories->isEmpty()) { $this->sendMessage($chatId, "هیچ دسته‌بندی فعالی وجود ندارد!"); return; }
        $inlineKeyboard = $categories->map(fn($c) => [['text' => $c->name, 'callback_data' => "category_{$c->id}"]])->toArray();
        $msgId = $this->sendMessage($chatId, "نوع گزارش را انتخاب کنید:", ['inline_keyboard' => $inlineKeyboard]);
        $state->update(['last_message_id' => $msgId]);
    }

    private function askNextField(string $chatId, BotState $state): void
    {
        $state->refresh();
        $field = $this->currentField($state);
        if (!$field) { $state->update(['step' => 'preview']); $this->showPreview($chatId, $state); return; }

        if ($field->type === 'option') {
            $this->askOptionField($chatId, $state, $field);
        } else {
            $msgId = $this->sendMessage($chatId, $this->buildFieldPrompt($field));
            $state->update(['last_message_id' => $msgId, 'step' => 'answering_field']);
        }
    }

    private function askOptionField(string $chatId, BotState $state, CategoryField $field): void
    {
        $options = $field->options;
        if ($options->isEmpty()) { $this->popField($state); $this->askNextField($chatId, $state); return; }

        $prompt = "لطفاً یکی را انتخاب کنید:\n\n🔹 *{$field->label}*";
        if ($field->description) $prompt .= "\n📝 _{$field->description}_";

        $inlineKeyboard = $options->map(fn($o) => [['text' => $o->label, 'callback_data' => "opt_{$o->id}"]])->toArray();
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
        $this->prependOptionFields($state, $option);
        $state->update(['draft_data' => $draft]);

        $this->deleteTrackedMessage($chatId, $state);
        $this->askNextField($chatId, $state);
    }

    private function saveAnswerAndContinue(string $chatId, string $text, BotState $state): void
    {
        $field = $this->currentField($state);
        if (!$field) return;

        if ($field->type === 'photo') { $this->sendMessage($chatId, "⚠️ برای این فیلد باید عکس ارسال کنید."); return; }

        if ($field->type === 'link') {
            if (!preg_match('/^(?:https?:\/\/)?(?:www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{2,6}\b(?:[-a-zA-Z0-9()@:%_\+.~#?&\/=]*)$/i', $text)) {
                $this->sendMessage($chatId, "⚠️ لینک معتبر نیست.\nمثال: `https://example.com`");
                return;
            }
        }

        if ($field->is_multiple) {
            $this->appendToMultiple($chatId, $state, $field, $text, false);
        } else {
            $draft   = $state->draft_data ?? [];
            $draft[] = ['field_id' => $field->id, 'label' => $field->label, 'type' => $field->type, 'value' => $text];
            $this->popField($state);
            $state->update(['draft_data' => $draft]);
            $this->deleteTrackedMessage($chatId, $state);
            $this->askNextField($chatId, $state);
        }
    }

    private function handleFileUpload(string $chatId, $photo, $document, BotState $state, bool $isEditing = false): void
    {
        $field = $this->currentField($state);
        if (!$field) return;

        if (in_array($field->type, ['text', 'link'])) { $this->sendMessage($chatId, "⚠️ این فیلد نیاز به پاسخ متنی دارد."); return; }

        $fileId   = $photo ? end($photo)['file_id'] : $document['file_id'];
        $fileName = $document['file_name'] ?? (uniqid() . '.jpg');
        $filePath = $this->downloadFile($fileId, $fileName);

        if (!$filePath) { $this->sendMessage($chatId, "⚠️ خطایی در آپلود فایل رخ داد. دوباره تلاش کنید."); return; }

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
            }
            $state->update(['draft_data' => $draft, 'step' => $isEditing ? 'preview' : 'answering_field']);
            $this->deleteTrackedMessage($chatId, $state);
            if ($isEditing) { $this->sendMessage($chatId, "✅ فایل ویرایش شد."); $this->showPreview($chatId, $state); }
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
        if (!$field) return;

        $draft = $state->draft_data ?? [];
        $found = false;
        foreach ($draft as $item) { if ($item['field_id'] === $field->id) { $found = true; break; } }

        if (!$found) { $this->sendMessage($chatId, "⚠️ لطفاً حداقل یک " . $this->fieldTypeName($field->type) . " ارسال کنید."); return; }

        $this->deleteTrackedMessage($chatId, $state);
        if ($isEditing) { $state->update(['step' => 'preview']); $this->showPreview($chatId, $state); }
        else { $this->popField($state); $this->askNextField($chatId, $state); }
    }

    private function saveEditedAnswer(string $chatId, string $text, BotState $state): void
    {
        $field = $this->currentField($state);
        if (!$field) return;
        if ($field->type === 'photo') { $this->sendMessage($chatId, "⚠️ برای ویرایش این فیلد باید عکس ارسال کنید."); return; }

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
        $this->sendMessage($chatId, "✅ ویرایش انجام شد.");
        $this->showPreview($chatId, $state);
    }

    private function startEditField(string $chatId, BotState $state, int $fieldId): void
    {
        $field = CategoryField::find($fieldId);
        if (!$field) return;

        if ($field->is_multiple) {
            $draft = $state->draft_data ?? [];
            foreach ($draft as &$item) { if ($item['field_id'] === $fieldId) { $item['value'] = []; break; } }
            unset($item);
            $state->update(['draft_data' => $draft]);
        }

        $state->update(['step' => 'editing_field', 'field_queue' => [$fieldId]]);
        $this->deleteTrackedMessage($chatId, $state);

        if ($field->type === 'option') $this->askOptionField($chatId, $state, $field);
        else { $msgId = $this->sendMessage($chatId, $this->buildFieldPrompt($field)); $state->update(['last_message_id' => $msgId]); }
    }

    // ==========================================
    // Preview & Save
    // ==========================================

    private function showPreview(string $chatId, BotState $state): void
    {
        $category       = Category::find($state->category_id);
        $draft          = $state->draft_data ?? [];
        $formattedMonth = $this->formatJalaliMonthName($state->jalali_month ?? '');

        $msg = "📄 *پیش‌نمایش گزارش شما*\nماه: $formattedMonth\nدسته: {$category->name}\n\n";
        foreach ($draft as $item) {
            $val     = $item['value'];
            $display = is_array($val)
                ? ($item['type'] === 'photo' ? count($val) . ' عکس آپلود شد' : '• ' . implode("\n• ", $val))
                : ($item['type'] === 'photo' ? '[عکس آپلود شد]' : $val);
            $msg .= "▫️ *{$item['label']}:*\n{$display}\n\n";
        }

        $keyboard = ['inline_keyboard' => [
            [['text' => '✅ تایید و ارسال نهایی', 'callback_data' => 'confirm_report']],
            [['text' => '✏️ ویرایش پاسخ‌ها',      'callback_data' => 'request_edit']],
        ]];

        $msgId = $this->sendMessage($chatId, $msg, $keyboard);
        $state->update(['last_message_id' => $msgId]);
    }

    private function showEditOptions(string $chatId, BotState $state): void
    {
        $draft          = $state->draft_data ?? [];
        $inlineKeyboard = array_map(fn($item) => [['text' => 'ویرایش: ' . $item['label'], 'callback_data' => "edit_field_{$item['field_id']}"]], $draft);
        $msgId = $this->sendMessage($chatId, "کدام بخش را می‌خواهید ویرایش کنید؟", ['inline_keyboard' => $inlineKeyboard]);
        $state->update(['last_message_id' => $msgId]);
    }

    private function saveFinalReport(string $chatId, BotState $state): void
    {
        $this->deleteTrackedMessage($chatId, $state);
        Report::create([
            'representative_id' => $state->representative_id,
            'department_id'     => $state->department_id,
            'category_id'       => $state->category_id,
            'jalali_month'      => $state->jalali_month,
            'data'              => $state->draft_data,
        ]);
        $state->update(['step' => 'idle', 'draft_data' => [], 'field_queue' => []]);
        $this->sendMessage($chatId, "🎉 گزارش شما با موفقیت ثبت شد!");
        $this->showMainMenu($chatId);
    }

    // ==========================================
    // Helpers
    // ==========================================

    private function buildFieldPrompt(CategoryField $field): string
    {
        $msg = "لطفاً پاسخ دهید:\n\n🔹 *{$field->label}*";
        if ($field->type === 'photo')
            $msg .= $field->is_multiple ? "\n\n(عکس‌ها را یکی یکی ارسال کنید — وقتی تمام شد دکمه پایان را بزنید)" : "\n\n(لطفاً *عکس* مربوطه را ارسال کنید)";
        elseif ($field->type === 'link')
            $msg .= "\n\n(لینک را با فرمت `https://...` ارسال کنید)";
        if (!empty($field->description))
            $msg .= "\n📝 _{$field->description}_";
        return $msg;
    }

    private function sendMultipleDonePrompt(string $chatId, BotState $state, CategoryField $field, int $count, bool $isEditing): void
    {
        $typeLabel    = $this->fieldTypeName($field->type);
        $doneCallback = $isEditing ? 'field_multiple_done_edit' : 'field_multiple_done';
        $text         = "✅ تاکنون *{$count} {$typeLabel}* دریافت شد.\n\nمی‌توانید {$typeLabel} دیگری ارسال کنید یا دکمه پایان را بزنید.";
        $keyboard     = ['inline_keyboard' => [[['text' => '✅ پایان ارسال این بخش — مرحله بعدی', 'callback_data' => $doneCallback]]]];
        $msgId        = $this->sendMessage($chatId, $text, $keyboard);
        BotState::where('chat_id', $chatId)->update(['last_message_id' => $msgId]);
    }

    private function fieldTypeName(string $type): string
    {
        return match ($type) { 'photo' => 'عکس', 'link' => 'لینک', default => 'آیتم' };
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
            $fileName    = 'uploads/' . uniqid() . '.' . $extension;
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
