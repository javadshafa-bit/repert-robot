@extends('layouts.billing')

@section('title', 'اشتراک و پرداخت')

@section('content')
    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <h1 class="text-lg font-bold text-gray-800 mb-4">وضعیت اشتراک</h1>

        <dl class="text-sm space-y-2">
            <div class="flex justify-between"><dt class="text-gray-500">وضعیت سازمان</dt><dd>{{ $tenant->status_label }}</dd></div>
            <div class="flex justify-between">
                <dt class="text-gray-500">پایان اشتراک</dt>
                <dd>
                    @if($tenant->is_unlimited)
                        نامحدود
                    @elseif($tenant->subscription_ends_at)
                        {{ jdate($tenant->subscription_ends_at->copy()->setTimezone('Asia/Tehran'))->format('Y/m/d') }}
                        <span class="text-gray-400">({{ $tenant->subscriptionDaysLeft() }} روز)</span>
                    @else
                        هنوز اشتراکی تهیه نشده است
                    @endif
                </dd>
            </div>
            <div class="flex justify-between"><dt class="text-gray-500">قیمت هر روز</dt><dd>{{ number_format($pricePerDay) }} تومان</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">حداقل مبلغ پرداخت</dt><dd>{{ number_format($minAmount) }} تومان</dd></div>
        </dl>

        @unless($tenant->hasActiveSubscription())
            <p class="mt-4 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg p-3">
                تا زمانی که اشتراک فعال نشود، پنل مدیریت و ربات سازمان شما کار نمی‌کنند.
            </p>
        @endunless
    </div>

    <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
        <h2 class="font-semibold text-gray-800 mb-4">تهیه یا تمدید اشتراک</h2>

        <form action="{{ route('billing.pay') }}" method="POST" id="pay-form" class="space-y-4">
            @csrf

            <div class="flex gap-4 text-sm">
                <label class="flex items-center gap-2">
                    <input type="radio" name="mode" value="days" checked class="js-mode"> بر اساس تعداد روز
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" name="mode" value="amount" class="js-mode"> بر اساس مبلغ دلخواه
                </label>
            </div>

            <div>
                <label class="block text-sm text-gray-600 mb-1" id="value-label">تعداد روز</label>
                <input type="number" name="value" id="value-input" min="1" value="{{ old('value', 30) }}"
                       class="w-full py-2 px-3 border border-gray-200 rounded-lg text-sm">
            </div>

            <div>
                <label class="block text-sm text-gray-600 mb-1">کد تخفیف (اختیاری)</label>
                <div class="flex gap-2">
                    <input type="text" name="discount_code" id="discount-input" value="{{ old('discount_code') }}"
                           class="flex-1 py-2 px-3 border border-gray-200 rounded-lg text-sm" dir="ltr">
                    <button type="button" id="apply-discount"
                            class="px-4 py-2 rounded-lg bg-gray-100 border border-gray-200 text-sm hover:bg-gray-200">اعمال کد</button>
                </div>
            </div>

            <div id="quote-box" class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-sm space-y-1">
                <div class="text-gray-500">برای دیدن مبلغ نهایی، مقدار را وارد کنید.</div>
            </div>

            <button type="submit" id="pay-button"
                    class="w-full py-2.5 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700 disabled:opacity-50">
                پرداخت و فعال‌سازی
            </button>
        </form>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-gray-800">پرداخت‌های اخیر</h2>
            <a href="{{ route('billing.invoices') }}" class="text-sm text-blue-600 hover:underline">همه‌ی پرداخت‌ها</a>
        </div>
        @include('billing._payments_table', ['payments' => $payments])
    </div>

    <script>
        (function () {
            const form     = document.getElementById('pay-form');
            const box      = document.getElementById('quote-box');
            const input    = document.getElementById('value-input');
            const label    = document.getElementById('value-label');
            const discount = document.getElementById('discount-input');
            const payBtn   = document.getElementById('pay-button');
            const token    = document.querySelector('meta[name="csrf-token"]').content;

            const money = n => new Intl.NumberFormat('fa-IR').format(n) + ' تومان';

            function currentMode() {
                return document.querySelector('.js-mode:checked').value;
            }

            async function refreshQuote() {
                const value = parseInt(input.value, 10);

                if (!value || value < 1) {
                    box.innerHTML = '<div class="text-gray-500">برای دیدن مبلغ نهایی، مقدار را وارد کنید.</div>';
                    return;
                }

                const response = await fetch('{{ route('billing.quote') }}', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token},
                    body: JSON.stringify({mode: currentMode(), value: value, discount_code: discount.value}),
                });

                if (!response.ok) return;

                const q = await response.json();
                let html = '';

                html += `<div class="flex justify-between"><span class="text-gray-500">مدت اشتراک</span><span>${q.days} روز</span></div>`;
                html += `<div class="flex justify-between"><span class="text-gray-500">مبلغ</span><span>${money(q.original_amount)}</span></div>`;

                if (q.discount_amount > 0) {
                    html += `<div class="flex justify-between text-green-700"><span>تخفیف ${q.discount_percent}٪ (${q.discount_code})</span><span>−${money(q.discount_amount)}</span></div>`;
                }

                html += `<div class="flex justify-between font-bold border-t border-gray-200 pt-1 mt-1"><span>قابل پرداخت</span><span>${money(q.amount)}</span></div>`;

                if (q.errors.length) {
                    html += '<ul class="text-red-700 list-disc pr-5 mt-2">' + q.errors.map(e => `<li>${e}</li>`).join('') + '</ul>';
                }

                box.innerHTML = html;
                payBtn.disabled    = q.errors.length > 0;
                // مبلغ صفر یعنی تخفیف کامل: کاربر به درگاه نمی‌رود
                payBtn.textContent = q.amount === 0 && !q.errors.length
                    ? 'فعال‌سازی رایگان با کد تخفیف'
                    : 'پرداخت و فعال‌سازی';
            }

            document.querySelectorAll('.js-mode').forEach(radio => radio.addEventListener('change', () => {
                const isDays = currentMode() === 'days';
                label.textContent = isDays ? 'تعداد روز' : 'مبلغ (تومان)';
                input.value = isDays ? 30 : {{ $minAmount }};
                refreshQuote();
            }));

            input.addEventListener('input', refreshQuote);
            document.getElementById('apply-discount').addEventListener('click', refreshQuote);
            discount.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); refreshQuote(); } });

            refreshQuote();
        })();
    </script>
@endsection
