@extends('layouts.platform')

@section('title', $code->exists ? 'ویرایش کد تخفیف' : 'کد تخفیف جدید')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-slate-800">{{ $code->exists ? 'ویرایش کد تخفیف' : 'کد تخفیف جدید' }}</h1>
        <a href="{{ route('platform.discount-codes.index') }}" class="text-sm text-blue-600 hover:underline">بازگشت</a>
    </div>

    <form action="{{ $code->exists ? route('platform.discount-codes.update', $code) : route('platform.discount-codes.store') }}"
          method="POST" class="bg-white border border-slate-200 rounded-xl p-6 space-y-4 max-w-2xl">
        @csrf
        @if($code->exists) @method('PUT') @endif

        <div>
            <label class="block text-sm text-slate-600 mb-1">کد</label>
            <input type="text" name="code" value="{{ old('code', $code->code) }}" dir="ltr"
                   class="w-full py-2 px-3 border border-slate-200 rounded-lg text-sm font-mono" placeholder="NOWRUZ1405">
            <p class="text-xs text-slate-400 mt-1">فقط حروف بزرگ انگلیسی، عدد، خط تیره و زیرخط. خودکار بزرگ می‌شود.</p>
        </div>

        <div>
            <label class="block text-sm text-slate-600 mb-1">درصد تخفیف</label>
            <input type="number" name="percent" min="1" max="100" value="{{ old('percent', $code->percent) }}"
                   class="w-full py-2 px-3 border border-slate-200 rounded-lg text-sm">
        </div>

        <div>
            <label class="block text-sm text-slate-600 mb-1">سقف استفاده</label>
            <input type="number" name="max_uses" min="1" value="{{ old('max_uses', $code->max_uses) }}"
                   class="w-full py-2 px-3 border border-slate-200 rounded-lg text-sm" placeholder="خالی = نامحدود">
            @if($code->exists)
                <p class="text-xs text-slate-400 mt-1">تا حالا {{ $code->used_count }} بار استفاده شده است.</p>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm text-slate-600 mb-1">شروع اعتبار (شمسی)</label>
                <input type="text" name="starts_at" dir="ltr" placeholder="1405/06/03"
                       value="{{ old('starts_at', \App\Support\JalaliDate::format($code->starts_at)) }}"
                       class="w-full py-2 px-3 border border-slate-200 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm text-slate-600 mb-1">پایان اعتبار (شمسی)</label>
                <input type="text" name="expires_at" dir="ltr" placeholder="1405/09/30"
                       value="{{ old('expires_at', \App\Support\JalaliDate::format($code->expires_at)) }}"
                       class="w-full py-2 px-3 border border-slate-200 rounded-lg text-sm">
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $code->is_active ?? true) ? 'checked' : '' }}>
            فعال باشد
        </label>

        <button class="px-5 py-2.5 rounded-lg bg-slate-900 text-white text-sm hover:bg-slate-800">ذخیره</button>
    </form>
@endsection
