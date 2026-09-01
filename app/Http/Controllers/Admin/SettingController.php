<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Models\Setting;
use App\Models\Tenant;
use App\Services\BotConnector;
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
            'guest_province_id'  => Setting::get('guest_province_id', ''),
        ];

        $provinces = Province::orderBy('name')->get();

        return view('admin.settings.index', compact('settings', 'flowSteps', 'tenant', 'provinces'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'bot_token'       => 'nullable|string|max:255',
            'require_representative_phone' => 'nullable|boolean',
            'bot_open_access'   => 'nullable|boolean',
            'open_access_phone_mode' => 'nullable|string|in:none,optional,required',
            'guest_province_id' => ['nullable', TenantRule::exists('provinces')],
        ]);

        if ($request->filled('bot_token')) {
            $this->tenant()->forceFill(['bot_token' => trim($request->bot_token)])->save();
        }

        Setting::set('require_representative_phone', $request->boolean('require_representative_phone') ? '1' : '0');
        Setting::set('bot_open_access', $request->boolean('bot_open_access') ? '1' : '0');
        Setting::set('open_access_phone_mode', $request->input('open_access_phone_mode', 'none'));
        Setting::set('guest_province_id', (string) ($request->input('guest_province_id') ?: ''));

        return back()->with('success', 'تنظیمات با موفقیت ذخیره شد.');
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
