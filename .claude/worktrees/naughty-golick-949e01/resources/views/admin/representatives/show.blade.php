@extends('layouts.app')

@section('content')
    <div class="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">پروفایل نماینده: {{ $representative->full_name }}</h2>
            <a href="{{ route('admin.representatives.index') }}" class="text-sm text-blue-600 hover:underline mt-2 inline-block">&larr; بازگشت به لیست</a>
        </div>
        <a href="{{ route('admin.representatives.edit', $representative) }}" class="py-2 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700">
            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
            ویرایش مشخصات
        </a>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- ستون سمت راست: اطلاعات کاربری -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white border rounded-xl shadow-sm p-6">
                <h3 class="font-bold text-gray-800 border-b pb-2 mb-4">مشخصات اصلی</h3>
                <ul class="space-y-4 text-sm">
                    <li><span class="text-gray-500 block text-xs mb-1">نام و نام خانوادگی:</span> <span class="font-semibold text-gray-800">{{ $representative->full_name }}</span></li>
                    <li><span class="text-gray-500 block text-xs mb-1">استان:</span> <span class="font-semibold text-gray-800">{{ $representative->province->name }}</span></li>
                    <li><span class="text-gray-500 block text-xs mb-1">شماره تماس بله:</span> <span class="font-semibold text-gray-800" dir="ltr">{{ $representative->phone_number }}</span></li>
                    <li>
                        <span class="text-gray-500 block text-xs mb-1">وضعیت اتصال به ربات:</span>
                        @if($representative->is_connected)
                            <span class="inline-flex items-center gap-1.5 py-1 px-2 mt-1 rounded-lg text-xs font-medium bg-green-100 text-green-800">متصل (Chat ID: {{ $representative->chat_id }})</span>
                        @else
                            <span class="inline-flex items-center gap-1.5 py-1 px-2 mt-1 rounded-lg text-xs font-medium bg-red-100 text-red-800">در انتظار اتصال</span>
                        @endif
                    </li>
                </ul>
            </div>

            <div class="bg-white border rounded-xl shadow-sm p-6">
                <h3 class="font-bold text-gray-800 border-b pb-2 mb-4">وضعیت اتمام ماه‌ها</h3>
                <p class="text-xs text-gray-500 mb-3">ماه‌هایی که این کاربر دکمه "پایان گزارش‌دهی" را زده است.</p>

                @if($representative->monthlyStatuses->count() > 0)
                    <ul class="space-y-2 text-sm">
                        @foreach($representative->monthlyStatuses as $status)
                            <li class="flex justify-between items-center bg-gray-50 p-2.5 rounded-lg border border-gray-100">
                                <span class="font-semibold text-gray-700" dir="ltr">{{ $status->jalali_month }}</span>
                                <span class="text-xs text-green-600 font-bold flex items-center gap-1">
                                    <svg class="size-3" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    بسته شده
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="text-sm text-gray-500 text-center py-4 bg-gray-50 rounded-lg border border-dashed">
                        هیچ ماهی تا کنون بسته نشده است.
                    </div>
                @endif
            </div>
        </div>

        <!-- ستون سمت چپ: گزارش‌های کاربر -->
        <div class="lg:col-span-2">
            <div class="bg-white border rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="font-bold text-gray-800">تاریخچه گزارش‌های ثبت شده</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">ماه</th>
                        <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">دسته‌بندی</th>
                        <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">تاریخ ثبت</th>
                        <th scope="col" class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase">عملیات</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                    @forelse($representative->reports as $report)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-800" dir="ltr">{{ $report->jalali_month }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">
                                <span class="inline-flex items-center gap-1.5 py-1 px-2 rounded-md text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">
                                    {{ $report->category->name }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" dir="ltr">
                                {{ \Morilog\Jalali\Jalalian::fromCarbon($report->created_at)->format('Y/m/d H:i') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-end text-sm">
                                <a href="{{ route('admin.reports.show', $report) }}" class="inline-flex items-center gap-x-1 text-blue-600 hover:text-blue-800 font-semibold">
                                    مشاهده جزئیات
                                    <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center">
                                <svg class="size-10 text-gray-300 mx-auto mb-3" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/></svg>
                                <p class="text-gray-500 text-sm">این نماینده تاکنون هیچ گزارشی ثبت نکرده است.</p>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection