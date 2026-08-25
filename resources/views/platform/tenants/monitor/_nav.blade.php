{{-- نوار پیمایش صفحات نظارتی یک سازمان --}}
<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-xl font-bold text-slate-800">{{ $tenant->name }}</h1>
        <p class="text-xs text-slate-500 mt-1">نمای فقط‌خواندنی — هیچ تغییری از این صفحات ممکن نیست.</p>
    </div>
    <a href="{{ route('platform.tenants.index') }}" class="text-sm text-blue-600 hover:underline">لیست سازمان‌ها</a>
</div>

@php $tabs = [
    'platform.tenants.show'              => 'خلاصه',
    'platform.tenants.representatives'   => 'نمایندگان',
    'platform.tenants.departments'       => 'دپارتمان‌ها',
    'platform.tenants.categories'        => 'دسته‌بندی‌ها',
    'platform.tenants.reports'           => 'گزارش‌ها',
    'platform.tenants.broadcasts'        => 'پیام همگانی',
    'platform.tenants.payments'          => 'پرداخت‌ها',
    'platform.tenants.subscription-logs' => 'تاریخچه‌ی اشتراک',
]; @endphp

<div class="flex flex-wrap gap-2 mb-6">
    @foreach($tabs as $route => $label)
        <a href="{{ route($route, $tenant) }}"
           class="px-3 py-1.5 rounded-lg text-sm border {{ request()->routeIs($route) ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50' }}">
            {{ $label }}
        </a>
    @endforeach
</div>
