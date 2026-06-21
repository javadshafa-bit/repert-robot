@extends('layouts.app')

@section('content')
    <!-- هدر + فیلتر استان -->
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">داشبورد خلاصه وضعیت</h2>
            <p class="text-sm text-gray-500 mt-1">نمای کلی از وضعیت سیستم و گزارش‌های دریافتی</p>
        </div>

        <!-- فیلتر استان -->
        <form method="GET" action="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
            <label class="text-sm text-gray-600 whitespace-nowrap">فیلتر استان:</label>
            <select name="province_id"
                    onchange="this.form.submit()"
                    class="py-2 px-3 border border-gray-200 rounded-lg text-sm bg-white shadow-sm focus:ring-2 focus:ring-blue-300 focus:border-blue-400 min-w-36">
                <option value="">همه استان‌ها</option>
                @foreach($provinces as $p)
                    <option value="{{ $p->id }}" {{ $provinceId == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                @endforeach
            </select>
            @if($provinceId)
                <a href="{{ route('admin.dashboard') }}"
                   class="py-2 px-3 text-xs text-gray-500 bg-gray-100 hover:bg-gray-200 rounded-lg whitespace-nowrap transition-colors">
                    پاک کردن
                </a>
            @endif
        </form>
    </div>

    @if($provinceId)
        @php $selectedName = $provinces->firstWhere('id', $provinceId)?->name; @endphp
        <div class="mb-4 flex items-center gap-2 text-sm text-blue-700 bg-blue-50 border border-blue-200 rounded-lg px-4 py-2.5">
            <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="3 11 22 2 13 21 11 13 3 11"/>
            </svg>
            نمودارها بر اساس استان <strong>{{ $selectedName }}</strong> فیلتر شده‌اند. کارت‌های آماری بالا کلی هستند.
        </div>
    @endif

    <!-- کارت‌های آماری -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- کل نمایندگان -->
        <a href="{{ route('admin.representatives.index') }}"
           class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl hover:shadow-md transition-shadow p-5 group">
            <div class="flex items-center justify-between mb-3">
                <div class="flex-shrink-0 size-10 bg-blue-100 rounded-xl flex items-center justify-center group-hover:bg-blue-200 transition-colors">
                    <svg class="size-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <span class="text-xs text-gray-400 bg-gray-50 px-2 py-1 rounded-lg">نماینده</span>
            </div>
            <div class="mt-auto">
                <p class="text-3xl font-bold text-gray-800">{{ $totalReps }}</p>
                <p class="text-xs text-gray-500 mt-1">کل نمایندگان</p>
            </div>
        </a>

        <!-- نمایندگان متصل -->
        <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="flex-shrink-0 size-10 bg-emerald-100 rounded-xl flex items-center justify-center">
                    <svg class="size-5 text-emerald-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.99 12 19.79 19.79 0 0 1 1.93 3.26a2 2 0 0 1 1.99-2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                </div>
                <span class="text-xs text-gray-400 bg-gray-50 px-2 py-1 rounded-lg">متصل</span>
            </div>
            <div class="mt-auto">
                <p class="text-3xl font-bold text-emerald-600">{{ $connectedReps }}</p>
                <p class="text-xs text-gray-500 mt-1">متصل به ربات</p>
            </div>
            @if($totalReps > 0)
            <div class="mt-3">
                <div class="flex justify-between text-xs text-gray-400 mb-1">
                    <span>درصد اتصال</span>
                    <span>{{ round($connectedReps / $totalReps * 100) }}%</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-1.5">
                    <div class="bg-emerald-500 h-1.5 rounded-full" style="width: {{ round($connectedReps / $totalReps * 100) }}%"></div>
                </div>
            </div>
            @endif
        </div>

        <!-- کل گزارش‌ها -->
        <a href="{{ route('admin.reports.index') }}"
           class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl hover:shadow-md transition-shadow p-5 group">
            <div class="flex items-center justify-between mb-3">
                <div class="flex-shrink-0 size-10 bg-violet-100 rounded-xl flex items-center justify-center group-hover:bg-violet-200 transition-colors">
                    <svg class="size-5 text-violet-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                </div>
                <span class="text-xs text-gray-400 bg-gray-50 px-2 py-1 rounded-lg">گزارش</span>
            </div>
            <div class="mt-auto">
                <p class="text-3xl font-bold text-violet-600">{{ $totalReports }}</p>
                <p class="text-xs text-gray-500 mt-1">کل گزارش‌های ثبت‌شده</p>
            </div>
        </a>

        <!-- دسته‌بندی‌ها -->
        <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="flex-shrink-0 size-10 bg-amber-100 rounded-xl flex items-center justify-center">
                    <svg class="size-5 text-amber-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/>
                        <rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/>
                    </svg>
                </div>
                <span class="text-xs text-gray-400 bg-gray-50 px-2 py-1 rounded-lg">دسته</span>
            </div>
            <div class="mt-auto">
                <p class="text-3xl font-bold text-amber-600">{{ $totalCategories }}</p>
                <p class="text-xs text-gray-500 mt-1">دسته‌بندی فعال</p>
            </div>
        </div>
    </div>

    <!-- ردیف اول نمودارها -->
    <div class="grid lg:grid-cols-3 gap-4 mb-4">

        <!-- نمودار ستونی: گزارش‌ها بر اساس ماه -->
        <div class="lg:col-span-2 bg-white border border-gray-200 shadow-sm rounded-xl p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-bold text-gray-800">گزارش‌ها بر اساس ماه</h3>
                    <p class="text-xs text-gray-400 mt-0.5">
                        آخرین ماه‌های ثبت‌شده
                        @if($provinceId) · <span class="text-blue-500">فیلتر شده</span> @endif
                    </p>
                </div>
                <div class="flex-shrink-0 size-8 bg-indigo-50 rounded-lg flex items-center justify-center">
                    <svg class="size-4 text-indigo-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/>
                        <line x1="6" y1="20" x2="6" y2="14"/><line x1="2" y1="20" x2="22" y2="20"/>
                    </svg>
                </div>
            </div>
            @if($reportsByMonth->isEmpty())
                <div class="flex items-center justify-center h-48 text-gray-400 text-sm">داده‌ای برای نمایش وجود ندارد</div>
            @else
                <div id="chart-reports-month"></div>
            @endif
        </div>

        <!-- نمودار دونات: توزیع گزارش‌ها بر اساس استان (همیشه کلی) -->
        <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-bold text-gray-800">توزیع گزارش‌ها بر اساس استان</h3>
                    <p class="text-xs text-gray-400 mt-0.5">کلی · بدون فیلتر</p>
                </div>
                <div class="flex-shrink-0 size-8 bg-cyan-50 rounded-lg flex items-center justify-center">
                    <svg class="size-4 text-cyan-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/>
                    </svg>
                </div>
            </div>
            @if($reportsByProvince->isEmpty())
                <div class="flex items-center justify-center h-48 text-gray-400 text-sm">داده‌ای برای نمایش وجود ندارد</div>
            @else
                <div id="chart-reports-province"></div>
            @endif
        </div>
    </div>

    <!-- ردیف دوم نمودارها -->
    <div class="grid lg:grid-cols-2 gap-4">

        <!-- نمودار دونات: توزیع گزارش‌ها بر اساس دپارتمان -->
        <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-bold text-gray-800">توزیع گزارش‌ها بر اساس دپارتمان</h3>
                    <p class="text-xs text-gray-400 mt-0.5">
                        سهم هر دپارتمان
                        @if($provinceId) · <span class="text-blue-500">فیلتر شده</span> @endif
                    </p>
                </div>
                <div class="flex-shrink-0 size-8 bg-violet-50 rounded-lg flex items-center justify-center">
                    <svg class="size-4 text-violet-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/>
                    </svg>
                </div>
            </div>
            @if($reportsByDept->isEmpty())
                <div class="flex items-center justify-center h-56 text-gray-400 text-sm">داده‌ای برای نمایش وجود ندارد</div>
            @else
                <div id="chart-reports-dept"></div>
            @endif
        </div>

        <!-- نمودار ستونی افقی: گزارش‌ها بر اساس دسته‌بندی -->
        <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-bold text-gray-800">گزارش‌ها بر اساس دسته‌بندی</h3>
                    <p class="text-xs text-gray-400 mt-0.5">
                        پرتکرارترین دسته‌بندی‌ها
                        @if($provinceId) · <span class="text-blue-500">فیلتر شده</span> @endif
                    </p>
                </div>
                <div class="flex-shrink-0 size-8 bg-amber-50 rounded-lg flex items-center justify-center">
                    <svg class="size-4 text-amber-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/>
                        <line x1="6" y1="20" x2="6" y2="14"/><line x1="2" y1="20" x2="22" y2="20"/>
                    </svg>
                </div>
            </div>
            @if($reportsByCategory->isEmpty())
                <div class="flex items-center justify-center h-56 text-gray-400 text-sm">داده‌ای برای نمایش وجود ندارد</div>
            @else
                <div id="chart-reports-category"></div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const isDark     = document.documentElement.classList.contains('dark');
    const textColor  = isDark ? '#9ca3af' : '#6b7280';
    const gridColor  = isDark ? '#374151' : '#f3f4f6';
    const fontFamily = 'Vazirmatn, sans-serif';
    const chartColors = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#3b82f6', '#8b5cf6', '#06b6d4', '#f97316', '#ec4899', '#14b8a6'];

    // ===== نمودار ستونی: گزارش‌ها بر اساس ماه =====
    @if($reportsByMonth->isNotEmpty())
    (function () {
        const months = @json($reportsByMonth->pluck('jalali_month'));
        const counts = @json($reportsByMonth->pluck('total')->map(fn($v) => (int)$v));

        new ApexCharts(document.getElementById('chart-reports-month'), {
            chart: { type: 'bar', height: 240, fontFamily, toolbar: { show: false }, animations: { enabled: true, speed: 500 } },
            series: [{ name: 'تعداد گزارش', data: counts }],
            xaxis: { categories: months, labels: { style: { colors: textColor, fontFamily }, rotate: -20 } },
            yaxis: { labels: { style: { colors: textColor, fontFamily } } },
            colors: ['#6366f1'],
            plotOptions: { bar: { borderRadius: 6, columnWidth: '52%', dataLabels: { position: 'top' } } },
            dataLabels: { enabled: true, offsetY: -18, style: { fontSize: '11px', fontFamily, colors: [textColor] } },
            grid: { borderColor: gridColor, strokeDashArray: 4 },
            tooltip: { style: { fontFamily } }
        }).render();
    })();
    @endif

    // ===== نمودار دونات: توزیع بر اساس استان (کلی) =====
    @if($reportsByProvince->isNotEmpty())
    (function () {
        const labels = @json($reportsByProvince->pluck('name'));
        const counts = @json($reportsByProvince->pluck('total')->map(fn($v) => (int)$v));

        new ApexCharts(document.getElementById('chart-reports-province'), {
            chart: { type: 'donut', height: 240, fontFamily, toolbar: { show: false } },
            series: counts,
            labels: labels,
            colors: chartColors,
            plotOptions: { pie: { donut: { size: '65%', labels: {
                show: true,
                total: { show: true, label: 'کل', fontFamily, color: textColor, formatter: (w) => w.globals.seriesTotals.reduce((a,b) => a+b, 0) },
                value: { fontFamily, color: textColor }
            }}}},
            dataLabels: { enabled: false },
            legend: { position: 'bottom', fontFamily, labels: { colors: textColor }, itemMargin: { horizontal: 4, vertical: 2 } },
            tooltip: { style: { fontFamily } }
        }).render();
    })();
    @endif

    // ===== نمودار دونات: توزیع بر اساس دپارتمان =====
    @if($reportsByDept->isNotEmpty())
    (function () {
        const labels = @json($reportsByDept->pluck('name'));
        const counts = @json($reportsByDept->pluck('total')->map(fn($v) => (int)$v));

        new ApexCharts(document.getElementById('chart-reports-dept'), {
            chart: { type: 'donut', height: 270, fontFamily, toolbar: { show: false } },
            series: counts,
            labels: labels,
            colors: chartColors,
            plotOptions: { pie: { donut: { size: '62%', labels: {
                show: true,
                total: { show: true, label: 'کل گزارش', fontFamily, color: textColor, formatter: (w) => w.globals.seriesTotals.reduce((a,b) => a+b, 0) },
                value: { fontFamily, color: textColor }
            }}}},
            dataLabels: { enabled: false },
            legend: { position: 'bottom', fontFamily, labels: { colors: textColor }, itemMargin: { horizontal: 5, vertical: 3 } },
            tooltip: { style: { fontFamily } }
        }).render();
    })();
    @endif

    // ===== نمودار افقی: گزارش‌ها بر اساس دسته‌بندی =====
    @if($reportsByCategory->isNotEmpty())
    (function () {
        const labels = @json($reportsByCategory->pluck('name'));
        const counts = @json($reportsByCategory->pluck('total')->map(fn($v) => (int)$v));

        new ApexCharts(document.getElementById('chart-reports-category'), {
            chart: { type: 'bar', height: 270, fontFamily, toolbar: { show: false }, animations: { enabled: true, speed: 500 } },
            series: [{ name: 'تعداد گزارش', data: counts }],
            xaxis: { categories: labels, labels: { style: { colors: textColor, fontFamily } } },
            yaxis: { labels: { style: { colors: textColor, fontFamily } } },
            colors: ['#f59e0b'],
            plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '58%', dataLabels: { position: 'top' } } },
            dataLabels: { enabled: true, offsetX: 14, style: { fontSize: '11px', fontFamily, colors: [textColor] } },
            grid: { borderColor: gridColor, strokeDashArray: 4 },
            tooltip: { style: { fontFamily } }
        }).render();
    })();
    @endif
});
</script>
@endpush
