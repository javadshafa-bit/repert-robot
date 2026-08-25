@extends('layouts.platform')
@section('title', 'گزارش‌های ' . $tenant->name)
@section('content')
    @include('platform.tenants.monitor._nav')

    <div class="bg-white border border-slate-200 rounded-xl overflow-x-auto">
        <table class="w-full text-sm text-right whitespace-nowrap">
            <thead class="bg-slate-50 text-slate-600">
            <tr>
                <th class="px-4 py-3 font-medium">نماینده</th>
                <th class="px-4 py-3 font-medium">دسته‌بندی</th>
                <th class="px-4 py-3 font-medium">دپارتمان</th>
                <th class="px-4 py-3 font-medium">ماه</th>
                <th class="px-4 py-3 font-medium">تاریخ ثبت</th>
                <th class="px-4 py-3 font-medium"></th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($rows as $row)
                <tr>
                    <td class="px-4 py-3">{{ $row->representative?->full_name ?: '—' }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $row->category?->name ?: '—' }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $row->department?->name ?: '—' }}</td>
                    <td class="px-4 py-3 text-slate-500" dir="ltr">{{ $row->jalali_month ?: '—' }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ jdate($row->created_at->copy()->setTimezone('Asia/Tehran'))->format('Y/m/d H:i') }}</td>
                    <td class="px-4 py-3">
                        <a href="{{ route('platform.tenants.report', [$tenant, $row->id]) }}" class="text-blue-600 hover:underline text-xs">مشاهده</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">گزارشی ثبت نشده است.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $rows->links() }}</div>
@endsection
