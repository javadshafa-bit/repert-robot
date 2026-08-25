<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\Request;

/**
 * تنظیمات سطح پلتفرم: قیمت روزانه، حداقل مبلغ، و اتصال درگاه.
 * این‌ها ربطی به جدول per-tenant «settings» ندارند.
 */
class PlatformSettingController extends Controller
{
    public function edit()
    {
        return view('platform.settings', [
            'pricePerDay'  => PlatformSetting::int('price_per_day'),
            'minAmount'    => PlatformSetting::int('min_payment_amount'),
            'merchantId'   => (string) PlatformSetting::get('zarinpal_merchant_id'),
            'sandbox'      => PlatformSetting::bool('zarinpal_sandbox'),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'price_per_day'        => 'required|integer|min:1',
            'min_payment_amount'   => 'required|integer|min:1000',
            'zarinpal_merchant_id' => 'nullable|string|max:64',
            'zarinpal_sandbox'     => 'nullable|boolean',
        ], [
            'price_per_day.required'      => 'قیمت هر روز الزامی است.',
            'price_per_day.min'           => 'قیمت هر روز باید بزرگ‌تر از صفر باشد.',
            'min_payment_amount.min'      => 'حداقل مبلغ نمی‌تواند کمتر از ۱۰۰۰ تومان باشد.',
        ]);

        PlatformSetting::set('price_per_day', (int) $data['price_per_day']);
        PlatformSetting::set('min_payment_amount', (int) $data['min_payment_amount']);
        PlatformSetting::set('zarinpal_merchant_id', trim((string) ($data['zarinpal_merchant_id'] ?? '')));
        PlatformSetting::set('zarinpal_sandbox', $request->boolean('zarinpal_sandbox') ? '1' : '0');

        return back()->with('success', 'تنظیمات پلتفرم ذخیره شد.');
    }
}
