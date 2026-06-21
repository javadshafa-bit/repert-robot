@extends('layouts.app')

@section('content')
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-gray-800">جزئیات گزارش</h2>
        <a href="{{ route('admin.reports.index') }}" class="text-sm text-blue-600 hover:underline mt-2 inline-block">&larr;
            بازگشت به لیست</a>
    </div>

    <div class="grid md:grid-cols-3 gap-6">
        <div class="md:col-span-1">
            <div class="bg-gray-50 border rounded-xl shadow-sm p-6">
                <h3 class="font-bold text-gray-800 border-b pb-2 mb-4">اطلاعات فراداده</h3>
                <ul class="space-y-3 text-sm">
                    <li><span class="text-gray-500 block">نماینده:</span> <span
                                class="font-semibold">{{ $report->representative->full_name }}</span></li>
                    <li><span class="text-gray-500 block">استان:</span> <span
                                class="font-semibold">{{ $report->representative->province->name }}</span></li>
                    <li><span class="text-gray-500 block">ماه گزارش:</span> <span class="font-semibold"
                                                                                  dir="ltr">{{ $report->jalali_month }}</span>
                    </li>
                    <li><span class="text-gray-500 block">دسته‌بندی:</span> <span
                                class="font-semibold">{{ $report->category->name }}</span></li>
                    <li><span class="text-gray-500 block">تاریخ ثبت سیستم:</span> <span class="font-semibold"
                                                                                        dir="ltr">{{ \Morilog\Jalali\Jalalian::fromCarbon($report->created_at)->format('Y/m/d H:i') }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="md:col-span-2">
            <div class="bg-white border rounded-xl shadow-sm p-6">
                <h3 class="font-bold text-gray-800 border-b pb-2 mb-4">محتوای گزارش ارسالی در ربات</h3>
                <div class="space-y-6">
                    <div>
                        <span class="block text-sm font-bold text-gray-700 mb-1">
                            دپارتمان:
                        </span>
                        <div class="text-gray-800 bg-gray-50 p-3 rounded border text-sm">
                            <div class="whitespace-pre-wrap">{{ $report->department?->name ?? '-' }}</div>
                        </div>
                    </div>
                    @foreach($report->category->fields as $field)
                        <div>
                            <span class="block text-sm font-bold text-gray-700 mb-1">{{ $field->label }}:</span>
                            @php $value = $report->data[$field->id] ?? null; @endphp

                            @if($value)
                                <div class="text-gray-800 bg-gray-50 p-3 rounded border text-sm">
                                    @if($field->type === 'photo')
                                        <a href="{{ Storage::url($value) }}" target="_blank">
                                            <img src="{{ Storage::url($value) }}" alt="{{ $field->label }}"
                                                 class="max-w-xs rounded-md">
                                        </a>
                                    @elseif($field->type === 'document')
                                        <a href="{{ Storage::url($value) }}" target="_blank"
                                           class="text-blue-600 hover:underline">دانلود فایل</a>
                                    @else
                                        <div class="whitespace-pre-wrap">{{ $value }}</div>
                                    @endif
                                </div>
                            @else
                                <div class="text-gray-400 bg-gray-50 p-3 rounded border text-sm">---</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection
