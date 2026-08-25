@extends('layouts.billing')

@section('title', 'رسید پرداخت')

@section('content')
    @if($payment->isPaid())
        <div class="bg-white border border-green-200 rounded-xl p-6">
            <h1 class="text-lg font-bold text-green-800 mb-4">پرداخت با موفقیت انجام شد</h1>

            <dl class="text-sm space-y-2">
                <div class="flex justify-between"><dt class="text-gray-500">کد رهگیری</dt><dd dir="ltr" class="font-mono">{{ $payment->ref_id }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">مبلغ پرداخت‌شده</dt><dd>{{ number_format($payment->amount) }} تومان</dd></div>
                @if($payment->discount_amount > 0)
                    <div class="flex justify-between text-green-700"><dt>تخفیف اعمال‌شده</dt><dd>{{ number_format($payment->discount_amount) }} تومان</dd></div>
                @endif
                <div class="flex justify-between"><dt class="text-gray-500">مدت افزوده‌شده</dt><dd>{{ $payment->days_granted }} روز</dd></div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">اشتراک تا</dt>
                    <dd>
                        @if($tenant->is_unlimited)
                            نامحدود
                        @else
                            {{ jdate($tenant->subscription_ends_at->copy()->setTimezone('Asia/Tehran'))->format('Y/m/d') }}
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between"><dt class="text-gray-500">تاریخ</dt><dd>{{ jdate($payment->paid_at->copy()->setTimezone('Asia/Tehran'))->format('Y/m/d H:i') }}</dd></div>
            </dl>

            <a href="{{ route('admin.dashboard') }}"
               class="mt-6 inline-block px-5 py-2.5 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700">ورود به پنل</a>
        </div>
    @else
        <div class="bg-white border border-red-200 rounded-xl p-6">
            <h1 class="text-lg font-bold text-red-800 mb-2">پرداخت انجام نشد</h1>
            <p class="text-sm text-gray-600">
                وضعیت این تراکنش: {{ $payment->status_label }}.
                اگر مبلغی از حساب شما کم شده باشد، طبق قوانین بانکی حداکثر تا ۷۲ ساعت برمی‌گردد.
            </p>
            <a href="{{ route('billing.index') }}"
               class="mt-6 inline-block px-5 py-2.5 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700">تلاش دوباره</a>
        </div>
    @endif
@endsection
