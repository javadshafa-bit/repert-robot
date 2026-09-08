<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Models\Setting;
use App\Models\Tenant;
use App\Services\BotConnector;
use App\Support\Sms\SmsManager;
use App\Support\TenantContext;
use App\Support\TenantRule;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(private BotConnector $bot) {}

    /** مستأجر جاری (کاربر لاگین‌شده حتماً به یک مستأجر تاییدشده تعلق دارد) */
    private function tenant(): Tenant
    {
        return TenantContext::get();
    }

    public function index()
    {
        $tenant = $this->tenant();

        $flowSteps = json_decode(Setting::get('bot_flow_steps', '["month","department","category"]'), true)
            ?: ['month', 'department', 'category'];

        $settings = [
            // توکن ربات per-tenant است و روی رکورد سازمان ذخیره می‌شود، نه در جدول settings
            'bot_token'       => $tenant->bot_token,
            'bot_connected'   => $tenant->bot_connected_at ? '1' : '0',
            'bot_username'    => $tenant->bot_username,
            'webhook_url'     => $tenant->webhookUrl(),
            'require_representative_phone' => Setting::get('require_representative_phone', '1'),
            'bot_open_access'    => Setting::get('bot_open_access', '0'),
            'open_access_phone_mode' => Setting::get('open_access_phone_mode', 'none'),
            'bot_auth_method'        => Setting::get('bot_auth_method', 'contact'),
            'guest_province_id'  => Setting::get('guest_province_id', ''),
            'sms_source'         => app(SmsManager::class)->source(),
            // کلید هرگز کامل به صفحه برنمی‌گردد؛ فقط نشانه‌ای که ثبت شده یا نه
            'sms_api_key_set'    => filled(Setting::get('sms_api_key')),
            'sms_template'       => Setting::get('sms_template', ''),
        ];

        $provinces = Province::orderBy('name')->get();

        // وضعیت پیامک از کانتینر خوانده می‌شود، نه مستقیم از config — همان
        // چیزی را نشان می‌دهد که واقعاً هنگام ارسال استفاده خواهد شد.
        $sms = app(\App\Support\Sms\SmsSender::class);
        $smsStatus = [
            'name'       => $sms->name(),
            'configured' => $sms->isConfigured(),
            'is_log'     => $sms instanceof \App\Support\Sms\LogSms,
            'own'        => app(SmsManager::class)->source() === SmsManager::SOURCE_OWN,
        ];

        return view('admin.settings.index', compact('settings', 'flowSteps', 'tenant', 'provinces', 'smsStatus'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'bot_token'       => 'nullable|string|max:255',
            'require_representative_phone' => 'nullable|boolean',
            'bot_open_access'   => 'nullable|boolean',
            'open_access_phone_mode' => 'nullable|string|in:none,optional,required',
            'bot_auth_method'        => 'nullable|string|in:contact,otp,both',
            'guest_province_id' => ['nullable', TenantRule::exists('provinces')],
            'sms_source'        => 'nullable|string|in:platform,own',
            'sms_api_key'       => 'nullable|string|max:255',
            'sms_template'      => 'nullable|string|max:100',
        ]);

        if ($request->filled('bot_token')) {
            $this->tenant()->forceFill(['bot_token' => trim($request->bot_token)])->save();
        }

        Setting::set('require_representative_phone', $request->boolean('require_representative_phone') ? '1' : '0');
        Setting::set('bot_open_access', $request->boolean('bot_open_access') ? '1' : '0');
        Setting::set('open_access_phone_mode', $request->input('open_access_phone_mode', 'none'));
        Setting::set('bot_auth_method', $request->input('bot_auth_method', 'contact'));
        Setting::set('guest_province_id', (string) ($request->input('guest_province_id') ?: ''));

        Setting::set('sms_source', $request->input('sms_source', SmsManager::SOURCE_PLATFORM));
        Setting::set('sms_template', (string) ($request->input('sms_template') ?: ''));

        // فیلد خالی یعنی «دست نزن»، نه «پاک کن» — چون کلید هیچ‌وقت در فرم
        // نمایش داده نمی‌شود و ذخیره‌ی معمولی وگرنه هر بار پاکش می‌کرد.
        if ($request->filled('sms_api_key')) {
            Setting::set('sms_api_key', trim($request->input('sms_api_key')));
        }

        return back()->with('success', 'تنظیمات با موفقیت ذخیره شد.');
    }

    /** پاک کردن کلید پنل پیامک این سازمان */
    public function clearSmsKey()
    {
        Setting::set('sms_api_key', '');

        return back()->with('success', 'کلید پنل پیامک پاک شد.');
    }

    /**
     * ارسال یک پیامک آزمایشی با همان مسیری که ربات استفاده می‌کند.
     *
     * بدون این، اولین باری که مدیر می‌فهمد پنلش کار نمی‌کند، لحظه‌ای است که
     * یک نماینده‌ی واقعی نمی‌تواند وارد شود.
     */
    public function testSms(Request $request)
    {
        $request->validate(['test_phone' => 'required|string|max:20']);

        $phone = preg_replace('/[^0-9+]/', '', $this->toEnglishDigits(trim($request->test_phone)));
        if (str_starts_with($phone, '+98'))    $phone = '0' . substr($phone, 3);
        elseif (str_starts_with($phone, '98')) $phone = '0' . substr($phone, 2);
        elseif (str_starts_with($phone, '9'))  $phone = '0' . $phone;

        if (!preg_match('/^09\\d{9}$/', $phone)) {
            return back()->with('error', 'شماره‌ی واردشده معتبر نیست.');
        }

        $sender = app(\App\Support\Sms\SmsSender::class);

        if (!$sender->isConfigured()) {
            return back()->with('error', 'سرویس پیامک تنظیم نشده است.');
        }

        // کد واقعی نیست و هیچ‌جا ذخیره نمی‌شود؛ فقط برای دیدن اینکه پیامک می‌رسد
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        return $sender->sendOtp($phone, $code)
            ? back()->with('success', 'پیامک آزمایشی ارسال شد. اگر نرسید، الگو و اعتبار پنل را بررسی کنید.')
            : back()->with('error', 'ارسال ناموفق بود. جزئیات در لاگ سرور ثبت شد.');
    }

    private function toEnglishDigits(string $value): string
    {
        return strtr($value, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ]);
    }

    public function updateFlow(Request $request)
    {
        $request->validate([
            'steps'   => 'present|array|min:1|max:3',
            'steps.*' => 'required|string|in:month,department,category',
        ]);

        // always keep category in the flow
        $steps = array_values(array_unique($request->steps ?? []));
        if (!in_array('category', $steps)) $steps[] = 'category';

        Setting::set('bot_flow_steps', json_encode($steps));

        return response()->json(['success' => true]);
    }

    /** ثبت وبهوک اختصاصی این سازمان روی بله */
    public function connect()
    {
        $tenant = $this->tenant();

        if (!$tenant->bot_token) {
            return back()->with('error', 'ابتدا توکن ربات را وارد کنید.');
        }

        $result = $this->bot->setWebhook($tenant);

        return $result['ok']
            ? back()->with('success', $result['message'])
            : back()->with('error', 'خطا در اتصال: ' . $result['message']);
    }

    /** قطع اتصال ربات: حذف وبهوک روی بله + پاک کردن توکن */
    public function disconnect()
    {
        $this->bot->disconnect($this->tenant());

        return back()->with('success', 'اتصال ربات قطع شد.');
    }
}
