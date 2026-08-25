@extends('layouts.platform')
@section('title', 'پیام‌های همگانی ' . $tenant->name)
@section('content')
    @include('platform.tenants.monitor._nav')

    <div class="bg-white border border-slate-200 rounded-xl overflow-x-auto">
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 text-slate-600">
            <tr>
                <th class="px-4 py-3 font-medium">عنوان</th>
                <th class="px-4 py-3 font-medium">زمان‌بندی</th>
                <th class="px-4 py-3 font-medium">وضعیت</th>
                <th class="px-4 py-3 font-medium">ساخته‌شده</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($rows as $row)
                <tr>
                    <td class="px-4 py-3">{{ $row->title }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $row->schedule_label }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $row->status }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ jdate($row->created_at->copy()->setTimezone('Asia/Tehran'))->format('Y/m/d H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">پیام همگانی‌ای ثبت نشده است.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $rows->links() }}</div>
@endsection
