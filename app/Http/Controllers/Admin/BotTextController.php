<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\BotText;
use Illuminate\Http\Request;

/**
 * صفحه‌ی «متن‌های ربات»: مدیر سازمان هر پیام و متن دکمه‌ای که کاربر نهایی
 * در بله می‌بیند را از اینجا بازنویسی می‌کند. کلیدها و پیش‌فرض‌ها در
 * App\Support\BotText تعریف شده‌اند.
 */
class BotTextController extends Controller
{
    public function index()
    {
        return view('admin.bot-texts.index', [
            'catalog'  => BotText::catalog(),
            'values'   => BotText::all(),
            'defaults' => BotText::defaults(),
        ]);
    }

    public function update(Request $request)
    {
        $definitions = BotText::definitions();

        $request->validate([
            'texts'   => 'required|array',
            'texts.*' => 'nullable|string|max:2000',
        ], [
            'texts.*.max' => 'هر متن حداکثر می‌تواند ۲۰۰۰ کاراکتر باشد.',
        ]);

        foreach ($request->input('texts', []) as $key => $value) {
            // کلیدهای ناشناخته (دستکاری فرم) نادیده گرفته می‌شوند
            if (!isset($definitions[$key])) {
                continue;
            }

            $value = is_string($value) ? trim($value) : '';

            // خالی گذاشتن = بازگشت به پیش‌فرض
            Setting::set($key, $value === '' ? $definitions[$key]['default'] : $value);
        }

        BotText::flush();

        return back()->with('success', 'متن‌های ربات ذخیره شد.');
    }

    /** بازگرداندن همه‌ی متن‌ها به مقدار پیش‌فرض */
    public function reset()
    {
        foreach (BotText::defaults() as $key => $default) {
            Setting::set($key, $default);
        }

        BotText::flush();

        return back()->with('success', 'همه‌ی متن‌ها به حالت پیش‌فرض بازگشتند.');
    }
}
