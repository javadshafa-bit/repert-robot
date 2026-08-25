@extends('layouts.platform')

@section('title', 'تنظیمات پلتفرم')

@section('content')
    <h1 class="text-xl font-bold text-slate-800 mb-6">تنظیمات پلتفرم</h1>

    <form action="{{ route('platform.settings.update') }}" method="POST"
          class="bg-white border border-slate-200 rounded-xl p-6 space-y-5 max-w-2xl">
        @csrf

        <div>
            <label class="block text-sm text-slate-600 mb-1">قیمت هر روز اشتراک (تومان)</label>
            <input type="number" name="price_per_day" min="1" value="{{ old('price_per_day', $pricePerDay) }}"
                   class="w-full py-2 px-3 border border-slate-200 rounded-lg text-sm">
            <p class="text-xs text-slate-400 mt-1">تعداد روزِ هر پرداخت از همین عدد حساب می‌شود.</p>
        </div>

        <div>
            <label class="block text-sm text-slate-600 mb-1">حداقل مبلغ قابل پرداخت (تومان)</label>
            <input type="number" name="min_payment_amount" min="1000" value="{{ old('min_payment_amount', $minAmount) }}"
                   class="w-full py-2 px-3 border border-slate-200 rounded-lg text-sm">
        </div>

        <hr class="border-slate-100">

        <div>
            <label class="block text-sm text-slate-600 mb-1">شناسه پذیرنده‌ی زرین‌پال (Merchant ID)</label>
            <input type="text" name="zarinpal_merchant_id" dir="ltr"
                   value="{{ old('zarinpal_merchant_id', $merchantId) }}"
                   class="w-full py-2 px-3 border border-slate-200 rounded-lg text-sm font-mono">
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="zarinpal_sandbox" value="1" {{ old('zarinpal_sandbox', $sandbox) ? 'checked' : '' }}>
            حالت آزمایشی (sandbox) — پول واقعی جابه‌جا نمی‌شود
        </label>

        @if($sandbox)
            <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg p-3">
                درگاه در حالت آزمایشی است. تا وقتی این تیک برداشته نشود، هیچ پرداخت واقعی‌ای انجام نمی‌شود.
            </p>
        @endif

        <button class="px-5 py-2.5 rounded-lg bg-slate-900 text-white text-sm hover:bg-slate-800">ذخیره</button>
    </form>
@endsection
