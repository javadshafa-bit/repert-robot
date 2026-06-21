@extends('layouts.app')

@section('content')
    <div class="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">آرشیو گزارش‌ها</h2>
            <p class="text-sm text-gray-600 mt-1">مشاهده و بررسی گزارش‌های ثبت شده توسط نمایندگان</p>
        </div>

        <!-- فرم دریافت خروجی اکسل -->
        <form action="{{ route('admin.export.reports') }}" method="GET" class="flex gap-2 flex-wrap items-center"
              id="exportForm">
            <select name="month" id="export_month" class="py-2 px-3 border border-gray-200 rounded-lg text-sm" required>
                <option value="">انتخاب ماه</option>
                @foreach($months as $month)
                    <option value="{{ $month }}" {{ request('month') == $month ? 'selected' : '' }}>{{ $month }}</option>
                @endforeach
            </select>
            <select name="province_id" class="py-2 px-3 border border-gray-200 rounded-lg text-sm">
                <option value="">همه استان‌ها</option>
                @foreach($provinces as $province)
                    <option value="{{ $province->id }}" {{ request('province_id') == $province->id ? 'selected' : '' }}>{{ $province->name }}</option>
                @endforeach
            </select>
            <button type="submit"
                    class="py-2 px-4 inline-flex items-center gap-x-2 text-sm font-semibold rounded-lg bg-green-600 text-white hover:bg-green-700">
                <svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" x2="12" y1="15" y2="3"/>
                </svg>
                خروجی اکسل
            </button>
        </form>
    </div>

    <!-- فرم فیلتر -->
    <div class="bg-white border rounded-xl shadow-sm mb-6 p-4">
        <form action="{{ route('admin.reports.index') }}" method="GET" class="grid sm:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-medium mb-1">ماه شمسی</label>
                <select name="month" class="py-2 px-3 block w-full border border-gray-200 rounded-lg text-sm"
                        onchange="document.getElementById('export_month').value = this.value;">
                    <option value="">همه ماه‌ها</option>
                    @foreach($months as $month)
                        <option value="{{ $month }}" {{ request('month') == $month ? 'selected' : '' }}>{{ $month }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">استان</label>
                <select name="province_id" class="py-2 px-3 block w-full border border-gray-200 rounded-lg text-sm">
                    <option value="">همه</option>
                    @foreach($provinces as $province)
                        <option value="{{ $province->id }}" {{ request('province_id') == $province->id ? 'selected' : '' }}>{{ $province->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">دسته‌بندی</label>
                <select name="category_id" class="py-2 px-3 block w-full border border-gray-200 rounded-lg text-sm">
                    <option value="">همه</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit"
                        class="py-2 px-4 w-full bg-gray-800 text-white rounded-lg text-sm font-semibold hover:bg-gray-900">
                    فیلتر
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden overflow-x-scroll">
        <table class="min-w-full divide-y divide-gray-200 ">
            <thead class="bg-gray-50">
            <tr>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500">ماه</th>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500">نماینده / استان</th>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500">دپارتمان</th>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500">دسته‌بندی</th>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500">تاریخ ثبت</th>
                <th scope="col" class="px-6 py-3 text-end text-xs font-medium text-gray-500">مشاهده</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
            @forelse($reports as $report)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-800"
                        dir="ltr">{{ $report->jalali_month }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">
                        {{ $report->representative->full_name }}<br>
                        <span class="text-xs text-gray-500">{{ $report->representative->province->name }}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-800">
                        {{ $report->department?->name ?? '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">{{ $report->category->name }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" dir="ltr">
                        {{ \Morilog\Jalali\Jalalian::fromCarbon($report->created_at)->format('Y/m/d H:i') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-end text-sm">
                        <a href="{{ route('admin.reports.show', $report) }}"
                           class="text-blue-600 hover:text-blue-800 font-semibold">جزئیات کامل</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">گزارشی با این مشخصات یافت نشد.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
        <div class="px-6 py-4 border-t border-gray-200">{{ $reports->links() }}</div>
    </div>
@endsection
