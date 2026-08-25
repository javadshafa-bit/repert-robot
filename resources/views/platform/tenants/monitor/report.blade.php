@extends('layouts.platform')
@section('title', 'گزارش سازمان ' . $tenant->name)
@section('content')
    @include('platform.tenants.monitor._nav')

    <a href="{{ route('platform.tenants.reports', $tenant) }}" class="text-sm text-blue-600 hover:underline">بازگشت به لیست گزارش‌ها</a>

    <div class="grid gap-6 md:grid-cols-3 mt-4">
        <div class="bg-white border border-slate-200 rounded-xl p-5">
            <h2 class="font-semibold text-slate-800 mb-4">فراداده</h2>
            <dl class="text-sm space-y-2">
                <div class="flex justify-between"><dt class="text-slate-500">نماینده</dt><dd>{{ $row->representative?->full_name ?: '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">استان</dt><dd>{{ $row->representative?->province?->name ?: '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">دسته‌بندی</dt><dd>{{ $row->category?->name ?: '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">دپارتمان</dt><dd>{{ $row->department?->name ?: '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">ماه گزارش</dt><dd dir="ltr">{{ $row->jalali_month ?: '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">تاریخ ثبت</dt><dd>{{ jdate($row->created_at->copy()->setTimezone('Asia/Tehran'))->format('Y/m/d H:i') }}</dd></div>
            </dl>
        </div>

        <div class="md:col-span-2 bg-white border border-slate-200 rounded-xl p-5">
            <h2 class="font-semibold text-slate-800 mb-4">محتوای گزارش</h2>

            @php
                $raw = is_array($row->data) ? $row->data : [];
                // فرمت قدیمی {field_id: value} هم پشتیبانی می‌شود
                $items = (!empty($raw) && !isset($raw[0]))
                    ? collect($raw)->map(fn ($value, $id) => ['label' => "فیلد {$id}", 'type' => 'text', 'value' => $value])->values()->all()
                    : $raw;
            @endphp

            @forelse($items as $index => $item)
                @php
                    $label  = $item['label'] ?? ('فیلد ' . ($index + 1));
                    $type   = $item['type'] ?? 'text';
                    $value  = $item['value'] ?? null;
                    $values = is_array($value) ? array_filter($value) : ($value !== null && $value !== '' ? [$value] : []);
                @endphp
                <div class="mb-4">
                    <div class="text-sm font-semibold text-slate-700 mb-1">{{ $label }}</div>
                    @if(empty($values))
                        <div class="text-sm text-slate-400">—</div>
                    @elseif(in_array($type, ['photo', 'file']))
                        {{-- فایل‌ها عمداً قابل دانلود نیستند؛ این صفحه فقط نظارتی است --}}
                        <div class="text-sm text-slate-500">{{ count($values) }} فایل پیوست</div>
                    @else
                        <ul class="text-sm text-slate-700 space-y-1">
                            @foreach($values as $one)
                                <li class="break-all" @if($type === 'link') dir="ltr" @endif>{{ $one }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @empty
                <p class="text-sm text-slate-400">داده‌ای ثبت نشده است.</p>
            @endforelse
        </div>
    </div>
@endsection
