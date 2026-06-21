<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SettingController extends Controller
{
    public function index()
    {
        $flowSteps = json_decode(Setting::get('bot_flow_steps', '["month","department","category"]'), true)
            ?: ['month', 'department', 'category'];

        $settings = [
            'bot_token'       => Setting::get('bot_token'),
            'bot_connected'   => Setting::get('bot_connected', '0'),
            'welcome_message' => Setting::get('welcome_message', 'به ربات گزارش‌دهی خوش آمدید.'),
            'error_message'   => Setting::get('error_message', 'شما مجاز به استفاده از این ربات نیستید.'),
        ];

        return view('admin.settings.index', compact('settings', 'flowSteps'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'welcome_message' => 'required|string',
            'error_message'   => 'required|string',
        ]);

        if ($request->filled('bot_token')) {
            Setting::set('bot_token', $request->bot_token);
        }

        Setting::set('welcome_message', $request->welcome_message);
        Setting::set('error_message', $request->error_message);

        return back()->with('success', 'تنظیمات با موفقیت ذخیره شد.');
    }

    public function updateFlow(Request $request)
    {
        $request->validate([
            'steps'   => 'required|array|size:3',
            'steps.*' => 'required|string|in:month,department,category',
        ]);

        Setting::set('bot_flow_steps', json_encode($request->steps));

        return response()->json(['success' => true]);
    }

    public function connect()
    {
        $token = Setting::get('bot_token');
        if (!$token) {
            return back()->with('error', 'ابتدا توکن ربات را وارد کنید.');
        }

        $webhookUrl = url('/api/bot/webhook');

        $response = Http::post("https://tapi.bale.ai/bot{$token}/setWebhook", [
            'url' => $webhookUrl,
        ]);

        if ($response->successful() && $response->json('ok')) {
            Setting::set('bot_connected', '1');
            return back()->with('success', 'ربات با موفقیت متصل شد.');
        }

        Setting::set('bot_connected', '0');
        return back()->with('error', 'خطا در اتصال: ' . $response->json('description', 'خطای ناشناخته'));
    }
}
