@extends('layouts.platform')
@section('title', 'پرداخت‌های ' . $tenant->name)
@section('content')
    @include('platform.tenants.monitor._nav')

    <div class="bg-white border border-slate-200 rounded-xl overflow-x-auto">
        <table class="w-full text-sm text-right whitespace-nowrap">
            <thead class="bg-slate-50 text-slate-600">
            <tr>
                <th class="px-4 py-3 font-medium">مبلغ</th>
                <th class="px-4 py-3 font-medium">تخفیف</th>
                <th class="px-4 py-3 font-medium">روز</th>
                <th class="px-4 py-3 font-medium">وضعیت</th>
                <th class="px-4 py-3 font-medium">کد رهگیری</th>
                <th class="px-4 py-3 font-medium">تاریخ</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($rows as $row)
                <tr>
                    <td class="px-4 py-3">{{ number_format($row->amount) }} تومان</td>
                    <td class="px-4 py-3 text-slate-500">
                        {{ $row->discount_amount > 0 ? number_format($row->discount_amount) . ' تومان (' . ($row->discountCode?->code ?: '—') . ')' : '—' }}
                    </td>
                    <td class="px-4 py-3 text-slate-500">{{ $row->days_granted }}</td>
                    <td class="px-4 py-3">{{ $row->status_label }}</td>
                    <td class="px-4 py-3 text-slate-500" dir="ltr">{{ $row->ref_id ?: '—' }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ jdate(($row->paid_at ?: $row->created_at)->copy()->setTimezone('Asia/Tehran'))->format('Y/m/d H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">پرداختی ثبت نشده است.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $rows->links() }}</div>
@endsection
