@extends('layouts.platform')

@section('title', $tenant->name)

@section('content')
    @include('platform.tenants.monitor._nav')

    <div class="grid gap-6 md:grid-cols-2">
        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <h2 class="font-semibold text-slate-800 mb-4">اطلاعات سازمان</h2>
            <dl class="text-sm space-y-2">
                <div class="flex justify-between"><dt class="text-slate-500">وضعیت</dt><dd>{{ $tenant->status_label }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">مدیر</dt><dd>{{ $tenant->owner_name }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">ایمیل</dt><dd dir="ltr">{{ $tenant->email }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">تماس</dt><dd dir="ltr">{{ $tenant->owner_phone ?: '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">تاریخ ثبت‌نام</dt><dd>{{ jdate($tenant->created_at->copy()->setTimezone('Asia/Tehran'))->format('Y/m/d H:i') }}</dd></div>
                @if($tenant->suspended_at)
                    <div class="flex justify-between"><dt class="text-slate-500">تعلیق از</dt><dd>{{ jdate($tenant->suspended_at->copy()->setTimezone('Asia/Tehran'))->format('Y/m/d H:i') }}</dd></div>
                @endif
                @if($tenant->suspended_reason)
                    <div class="flex justify-between"><dt class="text-slate-500">دلیل تعلیق</dt><dd>{{ $tenant->suspended_reason }}</dd></div>
                @endif
            </dl>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <h2 class="font-semibold text-slate-800 mb-4">اشتراک، ربات و آمار</h2>
            <dl class="text-sm space-y-2">
                <div class="flex justify-between">
                    <dt class="text-slate-500">پایان اشتراک</dt>
                    <dd>
                        @if($tenant->is_unlimited)
                            نامحدود
                        @elseif($tenant->subscription_ends_at)
                            {{ jdate($tenant->subscription_ends_at->copy()->setTimezone('Asia/Tehran'))->format('Y/m/d') }}
                            <span class="text-slate-400">({{ $tenant->subscriptionDaysLeft() }} روز)</span>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                {{-- توکن ربات عمداً هیچ‌جا چاپ نمی‌شود؛ فقط نام کاربری و تاریخ اتصال --}}
                <div class="flex justify-between">
                    <dt class="text-slate-500">ربات</dt>
                    <dd>{{ $tenant->bot_username ? '@' . $tenant->bot_username : ($tenant->bot_connected_at ? 'متصل' : 'متصل نیست') }}</dd>
                </div>
                <div class="flex justify-between"><dt class="text-slate-500">آخرین اتصال ربات</dt><dd>{{ $tenant->bot_connected_at ? jdate($tenant->bot_connected_at->copy()->setTimezone('Asia/Tehran'))->format('Y/m/d H:i') : '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">کاربران پنل</dt><dd>{{ number_format($stats['users']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">نمایندگان</dt><dd>{{ number_format($stats['representatives']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">دسته‌بندی‌ها</dt><dd>{{ number_format($stats['categories']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">دپارتمان‌ها</dt><dd>{{ number_format($stats['departments']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">گزارش‌ها</dt><dd>{{ number_format($stats['reports']) }}</dd></div>
            </dl>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-5 mt-6">
        <h2 class="font-semibold text-slate-800 mb-4">مدیریت اشتراک</h2>

        <div class="flex flex-wrap gap-2 mb-4">
            @foreach(['۷ روز' => 7, '۱ ماه' => 30, '۳ ماه' => 90, '۱ سال' => 365] as $label => $days)
                <form action="{{ route('platform.tenants.subscription', $tenant) }}" method="POST">
                    @csrf
                    <input type="hidden" name="mode" value="extend">
                    <input type="hidden" name="days" value="{{ $days }}">
                    <button class="px-3 py-1.5 rounded-lg bg-green-600 text-white text-xs hover:bg-green-700">+ {{ $label }}</button>
                </form>
            @endforeach
        </div>

        <form action="{{ route('platform.tenants.subscription', $tenant) }}" method="POST" class="space-y-3">
            @csrf
            <input type="hidden" name="mode" value="set">

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-sm text-slate-600 mb-1">تاریخ پایان (شمسی)</label>
                    <input type="text" name="ends_at" dir="ltr" placeholder="1405/09/30"
                           value="{{ old('ends_at', \App\Support\JalaliDate::format($tenant->subscription_ends_at)) }}"
                           class="w-full py-2 px-3 border border-slate-200 rounded-lg text-sm">
                    <p class="text-xs text-slate-400 mt-1">تاریخ گذشته یعنی کوتاه کردن دوره (سازمان بلافاصله منقضی می‌شود).</p>
                </div>
                <div>
                    <label class="block text-sm text-slate-600 mb-1">توضیح (اختیاری)</label>
                    <input type="text" name="note" value="{{ old('note') }}"
                           class="w-full py-2 px-3 border border-slate-200 rounded-lg text-sm" placeholder="دلیل این تغییر">
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_unlimited" value="1" {{ old('is_unlimited', $tenant->is_unlimited) ? 'checked' : '' }}>
                اشتراک نامحدود (بدون محدودیت زمانی)
            </label>

            <button class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm hover:bg-slate-800">ذخیره‌ی دوره</button>
        </form>
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
        @if($tenant->status === \App\Models\Tenant::STATUS_SUSPENDED)
            <form action="{{ route('platform.tenants.resume', $tenant) }}" method="POST">
                @csrf
                <button class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700">رفع تعلیق</button>
            </form>
        @else
            <form action="{{ route('platform.tenants.suspend', $tenant) }}" method="POST" class="flex items-center gap-2">
                @csrf
                <input type="text" name="reason" placeholder="دلیل تعلیق (اختیاری)" class="py-2 px-3 border border-slate-200 rounded-lg text-sm">
                <button class="px-4 py-2 rounded-lg bg-amber-500 text-white text-sm hover:bg-amber-600">تعلیق سازمان</button>
            </form>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-2 mt-6">
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <h2 class="font-semibold text-slate-800 px-4 py-3 border-b border-slate-100 flex items-center justify-between">
                <span>پرداخت‌ها</span>
                <a href="{{ route('platform.tenants.payments', $tenant) }}" class="text-xs text-blue-600 hover:underline">همه</a>
            </h2>
            <table class="w-full text-sm text-right">
                <tbody class="divide-y divide-slate-100">
                @forelse($payments as $payment)
                    <tr>
                        <td class="px-4 py-3">{{ number_format($payment->amount) }} تومان</td>
                        <td class="px-4 py-3 text-slate-500">{{ $payment->days_granted }} روز</td>
                        <td class="px-4 py-3 text-slate-500">{{ \App\Models\Payment::statusLabels()[$payment->status] ?? $payment->status }}</td>
                        <td class="px-4 py-3 text-slate-500" dir="ltr">{{ $payment->ref_id ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-8 text-center text-slate-500">پرداختی ثبت نشده است.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <h2 class="font-semibold text-slate-800 px-4 py-3 border-b border-slate-100 flex items-center justify-between">
                <span>تاریخچه‌ی اشتراک</span>
                <a href="{{ route('platform.tenants.subscription-logs', $tenant) }}" class="text-xs text-blue-600 hover:underline">همه</a>
            </h2>
            <table class="w-full text-sm text-right">
                <tbody class="divide-y divide-slate-100">
                @forelse($logs as $log)
                    <tr>
                        <td class="px-4 py-3 text-slate-500">{{ \App\Models\SubscriptionLog::sourceLabels()[$log->source] ?? $log->source }}</td>
                        <td class="px-4 py-3">{{ $log->note ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $log->actor_name ?: 'سیستم' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ jdate(\Illuminate\Support\Carbon::parse($log->created_at)->setTimezone('Asia/Tehran'))->format('Y/m/d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-8 text-center text-slate-500">تغییری ثبت نشده است.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
