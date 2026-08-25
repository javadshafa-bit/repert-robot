@extends('layouts.platform')

@section('title', 'سازمان‌ها')

@section('content')
    <h1 class="text-xl font-bold text-slate-800 mb-4">سازمان‌ها</h1>

    <div class="flex flex-wrap items-center gap-2 mb-6">
        <a href="{{ route('platform.tenants.index', ['q' => $search]) }}"
           class="px-4 py-2 rounded-lg text-sm border {{ $status === null ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50' }}">
            همه <span class="text-xs opacity-75">({{ $counts->sum() }})</span>
        </a>
        @foreach(\App\Models\Tenant::statusLabels() as $key => $label)
            <a href="{{ route('platform.tenants.index', ['status' => $key, 'q' => $search]) }}"
               class="px-4 py-2 rounded-lg text-sm border {{ $status === $key ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50' }}">
                {{ $label }} <span class="text-xs opacity-75">({{ $counts[$key] ?? 0 }})</span>
            </a>
        @endforeach

        <form method="GET" class="ms-auto flex items-center gap-2">
            @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
            <input type="text" name="q" value="{{ $search }}" placeholder="نام یا ایمیل سازمان"
                   class="py-2 px-3 border border-slate-200 rounded-lg text-sm">
            <button class="px-3 py-2 rounded-lg bg-white border border-slate-200 text-sm hover:bg-slate-50">جستجو</button>
        </form>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl overflow-x-auto">
        <table class="w-full text-sm text-right whitespace-nowrap">
            <thead class="bg-slate-50 text-slate-600">
            <tr>
                <th class="px-4 py-3 font-medium">سازمان</th>
                <th class="px-4 py-3 font-medium">مدیر</th>
                <th class="px-4 py-3 font-medium">وضعیت</th>
                <th class="px-4 py-3 font-medium">پایان اشتراک</th>
                <th class="px-4 py-3 font-medium">ربات</th>
                <th class="px-4 py-3 font-medium">عملیات</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($tenants as $tenant)
                <tr>
                    <td class="px-4 py-3">
                        <a href="{{ route('platform.tenants.show', $tenant) }}" class="text-blue-600 hover:underline">{{ $tenant->name }}</a>
                        <div class="text-xs text-slate-400" dir="ltr">{{ $tenant->email }}</div>
                    </td>
                    <td class="px-4 py-3">{{ $tenant->owner_name }}</td>
                    <td class="px-4 py-3">
                        @php $badge = match($tenant->status) {
                            \App\Models\Tenant::STATUS_ACTIVE          => 'bg-green-100 text-green-800',
                            \App\Models\Tenant::STATUS_EXPIRED         => 'bg-amber-100 text-amber-800',
                            \App\Models\Tenant::STATUS_SUSPENDED       => 'bg-red-100 text-red-800',
                            default                                    => 'bg-slate-100 text-slate-700',
                        }; @endphp
                        <span class="px-2 py-1 rounded text-xs {{ $badge }}">{{ $tenant->status_label }}</span>
                    </td>
                    <td class="px-4 py-3">
                        @if($tenant->is_unlimited)
                            <span class="text-green-700">نامحدود</span>
                        @elseif($tenant->subscription_ends_at)
                            {{ jdate($tenant->subscription_ends_at->copy()->setTimezone('Asia/Tehran'))->format('Y/m/d') }}
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if($tenant->bot_connected_at)
                            <span class="text-green-700">متصل</span>
                        @else
                            <span class="text-slate-400">متصل نیست</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if($tenant->status === \App\Models\Tenant::STATUS_SUSPENDED)
                            <form action="{{ route('platform.tenants.resume', $tenant) }}" method="POST">
                                @csrf
                                <button class="px-3 py-1.5 rounded-lg bg-blue-600 text-white text-xs hover:bg-blue-700">رفع تعلیق</button>
                            </form>
                        @else
                            <form action="{{ route('platform.tenants.suspend', $tenant) }}" method="POST">
                                @csrf
                                <button class="px-3 py-1.5 rounded-lg bg-amber-500 text-white text-xs hover:bg-amber-600">تعلیق</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">موردی یافت نشد.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $tenants->links() }}</div>
@endsection
