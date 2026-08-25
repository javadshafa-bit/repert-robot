@extends('layouts.platform')
@section('title', 'دسته‌بندی‌های ' . $tenant->name)
@section('content')
    @include('platform.tenants.monitor._nav')

    <div class="bg-white border border-slate-200 rounded-xl overflow-x-auto">
        <table class="w-full text-sm text-right">
            <thead class="bg-slate-50 text-slate-600">
            <tr>
                <th class="px-4 py-3 font-medium">نام</th>
                <th class="px-4 py-3 font-medium">تعداد فیلد</th>
                <th class="px-4 py-3 font-medium">ترتیب</th>
                <th class="px-4 py-3 font-medium">نمایش در ربات</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($rows as $row)
                <tr>
                    <td class="px-4 py-3">{{ $row->name }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ number_format($row->fields_count) }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $row->sort_order }}</td>
                    <td class="px-4 py-3">{{ $row->is_active ? 'فعال' : 'غیرفعال' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">دسته‌بندی‌ای ثبت نشده است.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $rows->links() }}</div>
@endsection
