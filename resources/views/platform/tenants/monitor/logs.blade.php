@extends('layouts.platform')
@section('title', 'تاریخچه‌ی اشتراک ' . $tenant->name)
@section('content')
    @include('platform.tenants.monitor._nav')

    <div class="bg-white border border-slate-200 rounded-xl overflow-x-auto">
        <table class="w-full text-sm text-right whitespace-nowrap">
            <thead class="bg-slate-50 text-slate-600">
            <tr>
                <th class="px-4 py-3 font-medium">نوع</th>
                <th class="px-4 py-3 font-medium">از تاریخ</th>
                <th class="px-4 py-3 font-medium">به تاریخ</th>
                <th class="px-4 py-3 font-medium">وضعیت</th>
                <th class="px-4 py-3 font-medium">توضیح</th>
                <th class="px-4 py-3 font-medium">توسط</th>
                <th class="px-4 py-3 font-medium">زمان</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($rows as $row)
                <tr>
                    <td class="px-4 py-3">{{ $row->source_label }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $row->from_unlimited ? 'نامحدود' : (\App\Support\JalaliDate::format($row->from_ends_at) ?: '—') }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $row->to_unlimited ? 'نامحدود' : (\App\Support\JalaliDate::format($row->to_ends_at) ?: '—') }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $row->from_status }} → {{ $row->to_status }}</td>
                    <td class="px-4 py-3">{{ $row->note ?: '—' }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $row->user?->name ?: 'سیستم' }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ jdate($row->created_at->copy()->setTimezone('Asia/Tehran'))->format('Y/m/d H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">تغییری ثبت نشده است.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $rows->links() }}</div>
@endsection
