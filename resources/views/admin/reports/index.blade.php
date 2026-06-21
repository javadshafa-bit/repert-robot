@extends('layouts.app')

@section('content')
<div class="mb-6 flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-800">آرشیو گزارش‌ها</h2>
        <p class="text-sm text-gray-500 mt-1">{{ $reports->total() }} گزارش</p>
    </div>

    {{-- دکمه خروجی اکسل (با همان فیلترهای جاری) --}}
    <a href="{{ route('admin.export.reports', request()->query()) }}"
       class="inline-flex items-center gap-2 py-2 px-4 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 shrink-0">
        <svg class="size-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="7 10 12 15 17 10"/>
            <line x1="12" x2="12" y1="15" y2="3"/>
        </svg>
        خروجی Excel ({{ $reports->total() }} گزارش)
    </a>
</div>

{{-- فرم فیلتر --}}
<form action="{{ route('admin.reports.index') }}" method="GET" id="filter-form">
<div class="bg-white border rounded-xl shadow-sm mb-4 p-4">
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 items-end">
        <div>
            <label class="block text-xs font-medium mb-1">ماه</label>
            <select name="month" class="py-2 px-3 block w-full border border-gray-200 rounded-lg text-sm">
                <option value="">همه ماه‌ها</option>
                @foreach($months as $m)
                    <option value="{{ $m }}" {{ request('month') == $m ? 'selected' : '' }}>{{ $m }}</option>
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
            <label class="block text-xs font-medium mb-1">دپارتمان</label>
            <select name="department_id" class="py-2 px-3 block w-full border border-gray-200 rounded-lg text-sm">
                <option value="">همه</option>
                @foreach($departments as $dep)
                    <option value="{{ $dep->id }}" {{ request('department_id') == $dep->id ? 'selected' : '' }}>{{ $dep->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium mb-1">دسته‌بندی</label>
            <select name="category_id" onchange="this.form.submit()"
                    class="py-2 px-3 block w-full border border-gray-200 rounded-lg text-sm">
                <option value="">همه</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium mb-1">نماینده</label>
            <select name="representative_id" class="py-2 px-3 block w-full border border-gray-200 rounded-lg text-sm">
                <option value="">همه</option>
                @foreach($representatives as $rep)
                    <option value="{{ $rep->id }}" {{ request('representative_id') == $rep->id ? 'selected' : '' }}>{{ $rep->full_name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- فیلتر فیلد خاص (فقط وقتی دسته‌بندی انتخاب شده) --}}
    @if($filterFields->isNotEmpty())
    <div class="mt-3 pt-3 border-t border-gray-100 grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
        <div class="sm:col-span-1">
            <label class="block text-xs font-medium mb-1 text-purple-700">🔍 فیلتر بر اساس گزینه</label>
            <select name="filter_field_id" onchange="this.form.submit()"
                    class="py-2 px-3 block w-full border border-purple-300 rounded-lg text-sm bg-purple-50">
                <option value="">انتخاب فیلد گزینه‌ای...</option>
                @foreach($filterFields as $ff)
                    <option value="{{ $ff->id }}" {{ request('filter_field_id') == $ff->id ? 'selected' : '' }}>{{ $ff->label }}</option>
                @endforeach
            </select>
        </div>
        @if($filterOptions->isNotEmpty())
        <div class="sm:col-span-1">
            <label class="block text-xs font-medium mb-1 text-purple-700">گزینه</label>
            <select name="filter_value" class="py-2 px-3 block w-full border border-purple-300 rounded-lg text-sm bg-purple-50">
                <option value="">همه گزینه‌ها</option>
                @foreach($filterOptions as $fo)
                    <option value="{{ $fo->label }}" {{ request('filter_value') == $fo->label ? 'selected' : '' }}>{{ $fo->label }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="flex gap-2">
            <button type="submit" class="py-2 px-4 bg-purple-600 text-white rounded-lg text-sm font-semibold hover:bg-purple-700">اعمال فیلتر</button>
        </div>
    </div>
    @endif

    <div class="mt-3 flex gap-2">
        <button type="submit" class="py-2 px-4 bg-gray-800 text-white rounded-lg text-sm font-semibold hover:bg-gray-900">فیلتر</button>
        @if(request()->hasAny(['month','province_id','department_id','category_id','representative_id','filter_field_id','filter_value']))
            <a href="{{ route('admin.reports.index') }}" class="py-2 px-3 bg-gray-100 text-gray-600 rounded-lg text-sm hover:bg-gray-200">پاک کردن</a>
        @endif
    </div>
</div>
</form>

{{-- جدول گزارش‌ها --}}
<div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-5 py-3 text-start text-xs font-medium text-gray-500">ماه</th>
                <th class="px-5 py-3 text-start text-xs font-medium text-gray-500">نماینده / استان</th>
                <th class="px-5 py-3 text-start text-xs font-medium text-gray-500">دپارتمان</th>
                <th class="px-5 py-3 text-start text-xs font-medium text-gray-500">دسته‌بندی</th>
                <th class="px-5 py-3 text-start text-xs font-medium text-gray-500">خلاصه پاسخ‌ها</th>
                <th class="px-5 py-3 text-start text-xs font-medium text-gray-500">تاریخ ثبت</th>
                <th class="px-5 py-3 text-end   text-xs font-medium text-gray-500">عملیات</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
        @forelse($reports as $report)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-4 whitespace-nowrap text-sm font-bold text-gray-800" dir="ltr">{{ $report->jalali_month }}</td>
                <td class="px-5 py-4 whitespace-nowrap text-sm text-gray-800">
                    {{ $report->representative->full_name }}<br>
                    <span class="text-xs text-gray-400">{{ $report->representative->province->name }}</span>
                </td>
                <td class="px-5 py-4 whitespace-nowrap text-sm text-gray-700">{{ $report->department?->name ?? '—' }}</td>
                <td class="px-5 py-4 whitespace-nowrap text-sm text-gray-800">{{ $report->category->name }}</td>
                <td class="px-5 py-4 text-xs text-gray-500 max-w-xs">
                    @php $data = is_array($report->data) ? $report->data : []; @endphp
                    @foreach(array_slice($data, 0, 3) as $item)
                        <span class="inline-block max-w-[120px] truncate align-bottom">
                            <strong>{{ $item['label'] }}:</strong>
                            {{ is_array($item['value']) ? count($item['value']) . ' مورد' : Str::limit($item['value'], 30) }}
                        </span>
                        @if(!$loop->last) &nbsp;|&nbsp; @endif
                    @endforeach
                    @if(count($data) > 3) <span class="text-gray-400">...</span> @endif
                </td>
                <td class="px-5 py-4 whitespace-nowrap text-xs text-gray-400" dir="ltr">
                    {{ \Morilog\Jalali\Jalalian::fromCarbon($report->created_at)->format('Y/m/d H:i') }}
                </td>
                <td class="px-5 py-4 whitespace-nowrap text-end text-sm">
                    <div class="inline-flex items-center gap-x-2">
                        <a href="{{ route('admin.reports.show', $report) }}" class="text-blue-600 hover:text-blue-800 font-semibold">جزئیات</a>
                        @if(auth()->user()->hasPermission('reports'))
                        <button type="button"
                                onclick="openDeleteModal('{{ route('admin.reports.destroy', $report) }}', '{{ $report->representative->full_name }} - {{ $report->jalali_month }}')"
                                class="text-red-500 hover:text-red-700 font-semibold">حذف</button>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-6 py-10 text-center text-gray-400">گزارشی یافت نشد.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="px-6 py-4 border-t border-gray-100">{{ $reports->links() }}</div>
</div>

{{-- Modal حذف --}}
<div id="hs-delete-report-modal" class="hs-overlay hidden fixed inset-0 z-80 overflow-x-hidden overflow-y-auto" role="dialog" tabindex="-1">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-md sm:w-full m-3 sm:mx-auto min-h-[calc(100%-3.5rem)] flex items-center">
        <div class="flex flex-col bg-white border shadow-sm rounded-xl overflow-hidden w-full">
            <div class="flex justify-between items-center py-3 px-4 border-b bg-red-50">
                <h3 class="font-bold text-gray-800">تأیید حذف گزارش</h3>
                <button type="button" data-hs-overlay="#hs-delete-report-modal" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <div class="p-5">
                <p class="text-sm text-gray-700 mb-1">آیا از حذف این گزارش اطمینان دارید؟</p>
                <p id="delete-report-label" class="text-sm font-semibold text-gray-900 mb-3"></p>
                <p class="text-xs text-red-500">این عملیات برگشت‌پذیر نیست.</p>
            </div>
            <div class="flex justify-end gap-x-2 py-3 px-4 border-t bg-gray-50">
                <button type="button" data-hs-overlay="#hs-delete-report-modal"
                        class="py-2 px-4 text-sm border border-gray-200 rounded-lg bg-white text-gray-800 hover:bg-gray-50">انصراف</button>
                <form id="delete-report-form" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" class="py-2 px-4 text-sm font-semibold rounded-lg bg-red-500 text-white hover:bg-red-600">بله، حذف کن</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openDeleteModal(url, label) {
    document.getElementById('delete-report-form').action = url;
    document.getElementById('delete-report-label').textContent = label;
    const el = document.getElementById('hs-delete-report-modal');
    const modal = window.HSOverlay?.getInstance(el);
    if (modal) modal.open();
    else if (window.HSOverlay) HSOverlay.open(el);
    else el.classList.remove('hidden');
}
</script>
@endsection
