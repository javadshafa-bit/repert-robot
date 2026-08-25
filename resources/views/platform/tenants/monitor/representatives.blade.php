@extends('layouts.platform')
@section('title', 'نمایندگان ' . $tenant->name)
@section('content')
    @include('platform.tenants.monitor._nav')

    <div class="bg-white border border-slate-200 rounded-xl overflow-x-auto">
        <table class="w-full text-sm text-right whitespace-nowrap">
            <thead class="bg-slate-50 text-slate-600">
            <tr>
                <th class="px-4 py-3 font-medium">نام</th>
                <th class="px-4 py-3 font-medium">استان</th>
                <th class="px-4 py-3 font-medium">شماره تماس</th>
                <th class="px-4 py-3 font-medium">وضعیت ربات</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($rows as $row)
                <tr>
                    <td class="px-4 py-3">{{ $row->first_name }} {{ $row->last_name }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $row->province?->name ?: '—' }}</td>
                    <td class="px-4 py-3 text-slate-500" dir="ltr">{{ $row->phone_number }}</td>
                    <td class="px-4 py-3">
                        @if($row->chat_id)
                            <span class="text-green-700">متصل</span>
                        @else
                            <span class="text-slate-400">متصل نشده</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">نماینده‌ای ثبت نشده است.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $rows->links() }}</div>
@endsection
