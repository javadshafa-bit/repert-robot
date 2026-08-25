@extends('layouts.platform')

@section('title', 'داشبورد پلتفرم')

@section('content')
    <h1 class="text-xl font-bold text-slate-800 mb-6">داشبورد</h1>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        @foreach(\App\Models\Tenant::statusLabels() as $key => $label)
            <a href="{{ route('platform.tenants.index', ['status' => $key]) }}"
               class="bg-white border border-slate-200 rounded-xl p-4 hover:border-slate-300">
                <div class="text-slate-500 text-sm">{{ $label }}</div>
                <div class="text-2xl font-bold text-slate-800 mt-1">{{ number_format($counts[$key] ?? 0) }}</div>
            </a>
        @endforeach
    </div>

    <div class="grid gap-4 sm:grid-cols-3 mb-8">
        <div class="bg-white border border-slate-200 rounded-xl p-4">
            <div class="text-slate-500 text-sm">درآمد کل</div>
            <div class="text-2xl font-bold text-slate-800 mt-1">{{ number_format($totalRevenue) }} <span class="text-sm font-normal">تومان</span></div>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl p-4">
            <div class="text-slate-500 text-sm">درآمد ماه جاری</div>
            <div class="text-2xl font-bold text-slate-800 mt-1">{{ number_format($monthRevenue) }} <span class="text-sm font-normal">تومان</span></div>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl p-4">
            <div class="text-slate-500 text-sm">ربات‌های متصل</div>
            <div class="text-2xl font-bold text-slate-800 mt-1">{{ number_format($connectedBots) }}</div>
        </div>
    </div>

    @if($purgeCandidates->isNotEmpty())
        <div class="bg-white border border-amber-300 rounded-xl p-4 mb-8">
            <h2 class="font-semibold text-amber-800 mb-2">
                سازمان‌هایی که بیش از {{ \App\Console\Commands\ExpireSubscriptions::PURGE_WARNING_MONTHS }} ماه است منقضی‌اند
            </h2>
            <p class="text-xs text-slate-500 mb-3">داده‌ی این سازمان‌ها دست‌نخورده نگه داشته شده است؛ حذف فقط با تصمیم شما انجام می‌شود.</p>
            <ul class="text-sm space-y-1">
                @foreach($purgeCandidates as $tenant)
                    <li>
                        <a href="{{ route('platform.tenants.show', $tenant) }}" class="text-blue-600 hover:underline">{{ $tenant->name }}</a>
                        <span class="text-slate-400">— پایان اشتراک: {{ jdate($tenant->subscription_ends_at->copy()->setTimezone('Asia/Tehran'))->format('Y/m/d') }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <h2 class="font-semibold text-slate-800 px-4 py-3 border-b border-slate-100">پرداخت‌های اخیر</h2>
            <table class="w-full text-sm text-right">
                <tbody class="divide-y divide-slate-100">
                @forelse($recentPayments as $payment)
                    <tr>
                        <td class="px-4 py-3">
                            <a href="{{ route('platform.tenants.show', $payment->tenant_id) }}" class="text-blue-600 hover:underline">{{ $payment->tenant_name }}</a>
                        </td>
                        <td class="px-4 py-3">{{ number_format($payment->amount) }} تومان</td>
                        <td class="px-4 py-3 text-slate-500">{{ $payment->days_granted }} روز</td>
                        <td class="px-4 py-3 text-slate-500">
                            {{ $payment->paid_at ? jdate(\Illuminate\Support\Carbon::parse($payment->paid_at)->setTimezone('Asia/Tehran'))->format('Y/m/d H:i') : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-8 text-center text-slate-500">هنوز پرداختی ثبت نشده است.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <h2 class="font-semibold text-slate-800 px-4 py-3 border-b border-slate-100">اشتراک‌هایی که تا ۷ روز آینده تمام می‌شوند</h2>
            <table class="w-full text-sm text-right">
                <tbody class="divide-y divide-slate-100">
                @forelse($expiringSoon as $tenant)
                    <tr>
                        <td class="px-4 py-3">
                            <a href="{{ route('platform.tenants.show', $tenant) }}" class="text-blue-600 hover:underline">{{ $tenant->name }}</a>
                        </td>
                        <td class="px-4 py-3">{{ jdate($tenant->subscription_ends_at->copy()->setTimezone('Asia/Tehran'))->format('Y/m/d') }}</td>
                        <td class="px-4 py-3 text-amber-700">{{ $tenant->subscriptionDaysLeft() }} روز مانده</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-8 text-center text-slate-500">موردی نیست.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
