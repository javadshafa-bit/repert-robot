@extends('layouts.app')

@section('content')
    <div class="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
        <div class="flex items-start gap-4">
            <a href="{{ route('admin.departments.index') }}" class="mt-1 text-gray-400 hover:text-gray-600 transition-colors shrink-0">
                <svg class="size-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m15 18-6-6 6-6"/>
                </svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-gray-800">{{ $department->name }}</h2>
                <p class="text-sm text-gray-500 mt-1">پروفایل دپارتمان — اطلاعات و گزارش‌های مرتبط</p>
            </div>
        </div>
        <a href="{{ route('admin.departments.edit', $department) }}"
           class="shrink-0 py-2 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700">
            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>
            </svg>
            ویرایش دپارتمان
        </a>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">

        <!-- ستون اطلاعات -->
        <div class="lg:col-span-1 space-y-5">

            <!-- مشخصات اصلی -->
            <div class="bg-white border rounded-xl shadow-sm p-6">
                <h3 class="font-bold text-gray-800 border-b pb-2 mb-4">مشخصات دپارتمان</h3>
                <ul class="space-y-4 text-sm">
                    <li>
                        <span class="text-gray-400 block text-xs mb-1">نام دپارتمان</span>
                        <span class="font-semibold text-gray-800">{{ $department->name }}</span>
                    </li>
                    <li>
                        <span class="text-gray-400 block text-xs mb-1">ترتیب نمایش</span>
                        <span class="font-semibold text-gray-800">{{ $department->sort_order }}</span>
                    </li>
                    <li>
                        <span class="text-gray-400 block text-xs mb-1">وضعیت</span>
                        @if($department->is_active)
                            <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-lg text-xs font-medium bg-green-100 text-green-800">
                                <span class="size-1.5 rounded-full bg-green-500"></span> فعال
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-lg text-xs font-medium bg-red-100 text-red-800">
                                <span class="size-1.5 rounded-full bg-red-500"></span> غیرفعال
                            </span>
                        @endif
                    </li>
                    <li>
                        <span class="text-gray-400 block text-xs mb-1">تاریخ ایجاد</span>
                        <span class="font-semibold text-gray-800" dir="ltr">
                            {{ \Morilog\Jalali\Jalalian::fromCarbon($department->created_at)->format('Y/m/d') }}
                        </span>
                    </li>
                </ul>
            </div>

            <!-- آمار کلی -->
            <div class="bg-white border rounded-xl shadow-sm p-6">
                <h3 class="font-bold text-gray-800 border-b pb-2 mb-4">آمار کلی</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center bg-violet-50 rounded-lg p-3">
                        <span class="text-sm text-gray-600">کل گزارش‌ها</span>
                        <span class="text-lg font-bold text-violet-600">{{ $department->reports_count }}</span>
                    </div>
                    @php
                        $months = $reports->getCollection()->pluck('jalali_month')->unique()->count();
                    @endphp
                    <div class="flex justify-between items-center bg-blue-50 rounded-lg p-3">
                        <span class="text-sm text-gray-600">تعداد ماه‌های فعال</span>
                        <span class="text-lg font-bold text-blue-600">{{ $months }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ستون گزارش‌ها -->
        <div class="lg:col-span-2">
            <div class="bg-white border rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-bold text-gray-800">گزارش‌های این دپارتمان</h3>
                    <span class="text-xs text-gray-400 bg-gray-50 px-2 py-1 rounded-lg">{{ $department->reports_count }} مورد</span>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-start text-xs font-medium text-gray-500">ماه</th>
                        <th scope="col" class="px-5 py-3 text-start text-xs font-medium text-gray-500">نماینده / استان</th>
                        <th scope="col" class="px-5 py-3 text-start text-xs font-medium text-gray-500">دسته‌بندی</th>
                        <th scope="col" class="px-5 py-3 text-start text-xs font-medium text-gray-500">تاریخ ثبت</th>
                        <th scope="col" class="px-5 py-3 text-end   text-xs font-medium text-gray-500">مشاهده</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                    @forelse($reports as $report)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-4 whitespace-nowrap text-sm font-bold text-gray-800" dir="ltr">
                                {{ $report->jalali_month }}
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap text-sm text-gray-800">
                                {{ $report->representative->full_name }}<br>
                                <span class="text-xs text-gray-400">{{ $report->representative->province->name }}</span>
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap text-sm">
                                <span class="inline-flex items-center py-0.5 px-2 rounded-md text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">
                                    {{ $report->category->name }}
                                </span>
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap text-sm text-gray-400" dir="ltr">
                                {{ \Morilog\Jalali\Jalalian::fromCarbon($report->created_at)->format('Y/m/d') }}
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap text-end text-sm">
                                <a href="{{ route('admin.reports.show', $report) }}"
                                   class="inline-flex items-center gap-x-1 text-blue-600 hover:text-blue-800 font-semibold">
                                    جزئیات
                                    <svg class="shrink-0 size-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m15 18-6-6 6-6"/>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <svg class="size-10 text-gray-300 mx-auto mb-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                </svg>
                                <p class="text-gray-500 text-sm">هیچ گزارشی برای این دپارتمان ثبت نشده است.</p>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
                @if($reports->hasPages())
                    <div class="px-6 py-4 border-t border-gray-100">{{ $reports->links() }}</div>
                @endif
            </div>
        </div>
    </div>
@endsection
