@extends('layouts.app')

@section('content')
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-gray-800">داشبورد خلاصه وضعیت</h2>
        <p class="text-sm text-gray-600 mt-1">نمای کلی از وضعیت سیستم و گزارش‌های دریافتی</p>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        <!-- Card 1 -->
        <div class="flex flex-col bg-white border shadow-sm rounded-xl">
            <div class="p-4 md:p-5">
                <a href="{{ route('admin.representatives.index') }}">

                    <div class="flex items-center gap-x-2">
                        <p class="text-xs uppercase tracking-wide text-gray-500">کل نمایندگان</p>
                    </div>
                    <div class="mt-1 flex items-center gap-x-2">
                        <h3 class="text-xl sm:text-2xl font-medium text-gray-800">{{ $totalReps }} نفر</h3>
                    </div>
                </a>

            </div>
        </div>

        <!-- Card 2 -->
        <div class="flex flex-col bg-white border shadow-sm rounded-xl">
            <div class="p-4 md:p-5">
                <div class="flex items-center gap-x-2">
                    <p class="text-xs uppercase tracking-wide text-gray-500">نمایندگان متصل به ربات</p>
                </div>
                <div class="mt-1 flex items-center gap-x-2">
                    <h3 class="text-xl sm:text-2xl font-medium text-green-600">{{ $connectedReps }} نفر</h3>
                </div>
            </div>
        </div>

        <!-- Card 3 -->
        <div class="flex flex-col bg-white border shadow-sm rounded-xl">
            <div class="p-4 md:p-5">
                <a href="{{ route('admin.reports.index') }}">

                    <div class="flex items-center gap-x-2">
                        <p class="text-xs uppercase tracking-wide text-gray-500">کل گزارش‌های ثبت شده</p>
                    </div>
                    <div class="mt-1 flex items-center gap-x-2">
                        <h3 class="text-xl sm:text-2xl font-medium text-blue-600">{{ $totalReports }} مورد</h3>
                    </div>
                </a>

            </div>
        </div>

        <!-- Card 4 -->
        <div class="flex flex-col bg-white border shadow-sm rounded-xl">
            <div class="p-4 md:p-5">
                <div class="flex items-center gap-x-2">
                    <p class="text-xs uppercase tracking-wide text-gray-500">اتمام گزارش ماه جاری</p>
                </div>
                <div class="mt-1 flex items-center gap-x-2">
                    <h3 class="text-xl sm:text-2xl font-medium text-purple-600">{{ $closedThisMonth }} نماینده</h3>
                    <span class="text-sm text-gray-500 mr-2">(ماه: {{ $currentMonth }})</span>
                </div>
            </div>
        </div>
    </div>
@endsection