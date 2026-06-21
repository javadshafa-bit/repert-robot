<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BotState;
use App\Models\Category;
use App\Models\Department;
use App\Models\MonthlyStatus;
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
        $this->token = Setting::get('bot_token');
        $this->apiUrl = "https://tapi.bale.ai/bot{$this->token}/";
    }

    // --- متد اصلی دریافت Webhook ---
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

    // --- پردازش پیام‌های متنی و Contact ---
    private function processMessage($message)
    {
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? null;
        $photo = $message['photo'] ?? null;
        $document = $message['document'] ?? null;

        $state = BotState::firstOrCreate(['chat_id' => $chatId]);

        // دستور /start
        if ($text === '/start') {
            return $this->handleStart($chatId, $state);
        }

        // احراز هویت (ارسال شماره تماس)
        if (isset($message['contact'])) {
            return $this->handleContact($chatId, $message['contact']['phone_number'], $state);
        }

        // اگر کاربر مجاز نیست
        if (!$state->representative_id) {
            return $this->sendMessage($chatId, Setting::get('error_message', 'شما مجاز به استفاده نیستید.'));
        }

        // دکمه‌های منوی اصلی
        if ($text === '📝 ارسال گزارش جدید') {
            return $this->startReportFlow($chatId, $state);
        }
        if ($text === '✅ پایان گزارش‌دهی ماه') {
            return $this->endMonthlyReport($chatId, $state);
        }

        // پردازش State های فرم داینامیک
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

        // در غیر این صورت منوی اصلی را نمایش بده
        $this->showMainMenu($chatId);
    }

    // --- پردازش دکمه‌های شیشه‌ای (Inline Keyboard) ---
    private function processCallback($callbackQuery)
    {
        $chatId = $callbackQuery['message']['chat']['id'];
        $data = $callbackQuery['data'];
        $state = BotState::where('chat_id', $chatId)->first();

        if (!$state || !$state->representative_id) return;

        // انتخاب ماه
        if (str_starts_with($data, 'month_')) {
            $month = str_replace('month_', '', $data);
            $state->update(['jalali_month' => $month, 'step' => 'selecting_department']);
            $this->askDepartment($chatId);
        } // انتخاب دپارتمان
        elseif (str_starts_with($data, 'department_')) {
            $departmentId = str_replace('department_', '', $data);
            $state->update([
                'department_id' => $departmentId,
                'step' => 'selecting_category'
            ]);
            $this->askCategory($chatId);
        } // انتخاب دسته‌بندی
        elseif (str_starts_with($data, 'category_')) {
            $categoryId = str_replace('category_', '', $data);
            $state->update([
                'category_id' => $categoryId,
                'current_field_index' => 0,
                'draft_data' => [],
                'step' => 'answering_field'
            ]);
            $this->askNextField($chatId, $state);
        } // تایید نهایی پیش‌نمایش
        elseif ($data === 'confirm_report') {
            $this->saveFinalReport($chatId, $state);
        } // درخواست ویرایش (نمایش لیست فیلدها)
        elseif ($data === 'request_edit') {
            $this->showEditOptions($chatId, $state);
        } // انتخاب فیلد برای ویرایش
        elseif (str_starts_with($data, 'edit_field_')) {
            $fieldIndex = str_replace('edit_field_', '', $data);
            $state->update(['step' => 'editing_field', 'current_field_index' => $fieldIndex]);

            $category = Category::with('fields')->find($state->category_id);
            $field = $category->fields[$fieldIndex];
            $this->sendMessage($chatId, "مقدار جدید برای «{$field->label}» را ارسال کنید (می‌توانید متن، عکس یا فایل بفرستید):");
        }
    }

    // ==========================================
    // منطق‌های عملیاتی ربات
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
            'one_time_keyboard' => true
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
        BotState::where('chat_id', $chatId)->update(['step' => 'idle']);
        $keyboard = [
            'keyboard' => [[['text' => '📝 ارسال گزارش جدید'], ['text' => '✅ پایان گزارش‌دهی ماه']]],
            'resize_keyboard' => true
        ];
        $this->sendMessage($chatId, "لطفاً یک گزینه را انتخاب کنید:", $keyboard);
    }

    private function startReportFlow($chatId, $state)
    {
        $state->update(['step' => 'selecting_month']);
        $now = Jalalian::now();
        $months = [$now->format('Y-m'), $now->subMonths(1)->format('Y-m'), $now->subMonths(2)->format('Y-m')];
        $inlineKeyboard = [];
        foreach ($months as $m) {
            $formattedName = $this->formatJalaliMonthName($m);
            $inlineKeyboard[] = [['text' => "گزارش $formattedName", 'callback_data' => "month_$m"]];
        }
        $this->sendMessage($chatId, "ابتدا ماه گزارش را انتخاب کنید:", ['inline_keyboard' => $inlineKeyboard]);
    }


    private function askDepartment($chatId)
    {
        $Departments = Department::where('is_active', true)->orderBy('sort_order')->get();
        if ($Departments->isEmpty()) {
            return $this->sendMessage($chatId, "هیچ دپارتمان فعالی وجود ندارد!");
        }
        $inlineKeyboard = [];
        foreach ($Departments as $department) {
            $inlineKeyboard[] = [['text' => $department->name, 'callback_data' => "department_{$department->id}"]];
        }
        $this->sendMessage($chatId, "ابتدا دپارتمان مربوطه را انتخاب کنید.", ['inline_keyboard' => $inlineKeyboard]);
    }

    private function askCategory($chatId)
    {
        $categories = Category::where('is_active', true)->orderBy('sort_order')->get();
        if ($categories->isEmpty()) {
            return $this->sendMessage($chatId, "هیچ دسته‌بندی فعالی وجود ندارد!");
        }
        $inlineKeyboard = [];
        foreach ($categories as $cat) {
            $inlineKeyboard[] = [['text' => $cat->name, 'callback_data' => "category_{$cat->id}"]];
        }
        $this->sendMessage($chatId, "نوع گزارش (دسته‌بندی) را انتخاب کنید:", ['inline_keyboard' => $inlineKeyboard]);
    }

    private function askNextField($chatId, $state)
    {
        $category = Category::with('fields')->find($state->category_id);
        $fields = $category->fields;

        if ($state->current_field_index < $fields->count()) {
            $field = $fields[$state->current_field_index];
            $msg = "لطفاً پاسخ دهید:\n\n🔹 *" . $field->label . "*";
            if ($field->type === 'photo') {
                $msg .= "\n\n(لطفاً *عکس* مربوطه را ارسال کنید)";
            } elseif ($field->type === 'document') {
                $msg .= "\n\n(لطفاً *فایل* مربوطه را ارسال کنید)";
            }
            if (!empty($field->description)) {
                $msg .= "\n📝 _" . $field->description . "_";
            }
            $this->sendMessage($chatId, $msg);
        } else {
            $state->update(['step' => 'preview']);
            $this->showPreview($chatId, $state);
        }
    }

    private function saveAnswerAndAskNext($chatId, $text, $state)
    {
        $category = Category::with('fields')->find($state->category_id);
        $field = $category->fields[$state->current_field_index];

        if ($field->type !== 'text') {
            $this->sendMessage($chatId, "برای این فیلد باید عکس یا فایل ارسال کنید. لطفاً فایل را بفرستید.");
            return;
        }

        $draft = $state->draft_data ?? [];
        $draft[$field->id] = $text;

        $state->update([
            'draft_data' => $draft,
            'current_field_index' => $state->current_field_index + 1
        ]);

        $this->askNextField($chatId, $state);
    }

    private function handleFileUpload($chatId, $photo, $document, $state, $isEditing = false)
    {
        $category = Category::with('fields')->find($state->category_id);
        $field = $category->fields[$state->current_field_index];
//        if (($field->type !== 'photo' && $photo) || ($field->type !== 'document' && $document)) {
//            $this->sendMessage($chatId, "نوع فایل ارسالی با فیلد درخواستی مطابقت ندارد. لطفاً یک فایل متنی ارسال کنید.");
//            return;
//        }

        $fileId = $photo ? end($photo)['file_id'] : $document['file_id'];
        $fileName = $document['file_name'] ?? (uniqid() . '.jpg');

        $filePath = $this->downloadFile($fileId, $fileName);

        if ($filePath) {
            $draft = $state->draft_data ?? [];
            $draft[$field->id] = $filePath;
            $state->draft_data = $draft;

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
        } else {
            $this->sendMessage($chatId, "خطایی در آپلود فایل رخ داد. لطفاً دوباره تلاش کنید.");
        }
    }

    private function showPreview($chatId, $state)
    {
        $category = Category::with('fields')->find($state->category_id);
        $draft = $state->draft_data;
        $formattedMonth = $this->formatJalaliMonthName($state->jalali_month);

        $msg = "📄 *پیش‌نمایش گزارش شما*\nماه: $formattedMonth\nدسته: {$category->name}\n\n";

        foreach ($category->fields as $index => $field) {
            $val = $draft[$field->id] ?? '-';
            if ($field->type === 'photo') {
                $val = $val === '-' ? '-' : '[عکس آپلود شد]';
            } elseif ($field->type === 'document') {
                $val = $val === '-' ? '-' : '[فایل آپلود شد]';
            }
            $msg .= "▫️ *{$field->label}:*\n{$val}\n\n";
        }

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '✅ تایید و ارسال نهایی', 'callback_data' => 'confirm_report']],
                [['text' => '✏️ ویرایش پاسخ‌ها', 'callback_data' => 'request_edit']]
            ]
        ];

        $this->sendMessage($chatId, $msg, $keyboard);
    }

    private function showEditOptions($chatId, $state)
    {
        $category = Category::with('fields')->find($state->category_id);
        $inlineKeyboard = [];

        foreach ($category->fields as $index => $field) {
            $inlineKeyboard[] = [['text' => "ویرایش: " . $field->label, 'callback_data' => "edit_field_{$index}"]];
        }

        $this->sendMessage($chatId, "کدام بخش را می‌خواهید ویرایش کنید؟", ['inline_keyboard' => $inlineKeyboard]);
    }

    private function saveEditedAnswer($chatId, $text, $state)
    {
        $category = Category::with('fields')->find($state->category_id);
        $field = $category->fields[$state->current_field_index];

        if ($field->type !== 'text') {
            $this->sendMessage($chatId, "برای ویرایش این فیلد باید عکس یا فایل ارسال کنید.");
            return;
        }

        $draft = $state->draft_data;
        $draft[$field->id] = $text;

        $state->update([
            'draft_data' => $draft,
            'step' => 'preview'
        ]);

        $this->sendMessage($chatId, "✅ ویرایش انجام شد.");
        $this->showPreview($chatId, $state);
    }

    private function saveFinalReport($chatId, $state)
    {
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

    private function endMonthlyReport($chatId, $state)
    {
        $month = Jalalian::now()->format('Y-m');
        MonthlyStatus::updateOrCreate(
            ['representative_id' => $state->representative_id, 'jalali_month' => $month],
            ['closed_at' => now()]
        );

        $this->sendMessage($chatId, "✅ پایان گزارش‌دهی شما برای این ماه ثبت شد. خسته نباشید!");
        $this->showMainMenu($chatId);
    }

    // --- Helper Methods ---
    private function sendMessage($chatId, $text, $replyMarkup = null)
    {
        $data = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'Markdown'];
        if ($replyMarkup) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }
        Http::post($this->apiUrl . 'sendMessage', $data);
    }

    private function downloadFile($fileId, $originalName)
    {
        $response = Http::post($this->apiUrl . 'getFile', ['file_id' => $fileId]);
        $fileData = $response->json();

        if (isset($fileData['result']['file_path'])) {
            $fileUrl = "https://tapi.bale.ai/file/bot{$this->token}/" . $fileData['result']['file_path'];
            $fileContent = Http::get($fileUrl)->body();
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $fileName = 'uploads/' . uniqid() . '.' . $extension;
            Storage::disk('public')->put($fileName, $fileContent);
            return $fileName;
        }
        return null;
    }

    private function formatJalaliMonthName($monthString)
    {
        $parts = explode('-', $monthString);
        if (count($parts) != 2) return $monthString;
        $year = $parts[0];
        $month = (int)$parts[1];
        $monthsName = [1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'];
        return ($monthsName[$month] ?? '') . ' ' . $year;
    }
}
