@extends('layouts.app')

@section('content')
    <div class="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">آرشیو گزارش‌ها</h2>
            <p class="text-sm text-gray-600 mt-1">مشاهده و بررسی گزارش‌های ثبت شده توسط نمایندگان</p>
        </div>

        <!-- فرم دریافت خروجی اکسل -->
        <form action="{{ route('admin.export.reports') }}" method="GET"
              class="flex gap-2 flex-wrap items-end bg-gray-50 border rounded-xl p-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">ماه <span class="text-red-500">*</span></label>
                <select name="month" id="export_month"
                        class="py-2 px-3 border border-gray-200 rounded-lg text-sm" required>
                    <option value="">انتخاب ماه</option>
                    @foreach($months as $m)
                        <option value="{{ $m }}" {{ request('month') == $m ? 'selected' : '' }}>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">استان</label>
                <select name="province_id" class="py-2 px-3 border border-gray-200 rounded-lg text-sm">
                    <option value="">همه استان‌ها</option>
                    @foreach($provinces as $province)
                        <option value="{{ $province->id }}" {{ request('province_id') == $province->id ? 'selected' : '' }}>{{ $province->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit"
                    class="py-2 px-4 inline-flex items-center gap-x-2 text-sm font-semibold rounded-lg bg-green-600 text-white hover:bg-green-700 self-end">
                <svg class="size-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
        <form action="{{ route('admin.reports.index') }}" method="GET"
              class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 items-end">
            <div>
                <label class="block text-xs font-medium mb-1">ماه</label>
                <select name="month"
                        class="py-2 px-3 block w-full border border-gray-200 rounded-lg text-sm"
                        onchange="document.getElementById('export_month').value = this.value;">
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
                <select name="category_id" class="py-2 px-3 block w-full border border-gray-200 rounded-lg text-sm">
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
            <div class="flex gap-2">
                <button type="submit"
                        class="py-2 px-4 w-full bg-gray-800 text-white rounded-lg text-sm font-semibold hover:bg-gray-900">
                    فیلتر
                </button>
                @if(request()->hasAny(['month','province_id','department_id','category_id','representative_id']))
                    <a href="{{ route('admin.reports.index') }}"
                       class="py-2 px-3 bg-gray-100 text-gray-600 rounded-lg text-sm hover:bg-gray-200 whitespace-nowrap">پاک</a>
                @endif
            </div>
        </form>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-5 py-3 text-start text-xs font-medium text-gray-500">ماه</th>
                    <th scope="col" class="px-5 py-3 text-start text-xs font-medium text-gray-500">نماینده / استان</th>
                    <th scope="col" class="px-5 py-3 text-start text-xs font-medium text-gray-500">دپارتمان</th>
                    <th scope="col" class="px-5 py-3 text-start text-xs font-medium text-gray-500">دسته‌بندی</th>
                    <th scope="col" class="px-5 py-3 text-start text-xs font-medium text-gray-500">تاریخ ثبت</th>
                    <th scope="col" class="px-5 py-3 text-end   text-xs font-medium text-gray-500">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
            @forelse($reports as $report)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-4 whitespace-nowrap text-sm font-bold text-gray-800" dir="ltr">
                        {{ $report->jalali_month }}
                    </td>
                    <td class="px-5 py-4 whitespace-nowrap text-sm text-gray-800">
                        {{ $report->representative->full_name }}<br>
                        <span class="text-xs text-gray-400">{{ $report->representative->province->name }}</span>
                    </td>
                    <td class="px-5 py-4 whitespace-nowrap text-sm text-gray-700">
                        {{ $report->department?->name ?? '—' }}
                    </td>
                    <td class="px-5 py-4 whitespace-nowrap text-sm text-gray-800">
                        {{ $report->category->name }}
                    </td>
                    <td class="px-5 py-4 whitespace-nowrap text-sm text-gray-400" dir="ltr">
                        {{ \Morilog\Jalali\Jalalian::fromCarbon($report->created_at)->format('Y/m/d H:i') }}
                    </td>
                    <td class="px-5 py-4 whitespace-nowrap text-end text-sm">
                        <div class="inline-flex items-center gap-x-2">
                            <a href="{{ route('admin.reports.show', $report) }}"
                               class="text-blue-600 hover:text-blue-800 font-semibold">جزئیات</a>

                            @if(auth()->user()->hasPermission('reports'))
                            <button type="button"
                                    onclick="openDeleteModal('{{ route('admin.reports.destroy', $report) }}', '{{ $report->representative->full_name }} - {{ $report->jalali_month }}')"
                                    class="inline-flex items-center gap-x-1 text-red-500 hover:text-red-700 font-semibold transition-colors">
                                <svg class="size-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/>
                                    <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                                </svg>
                                حذف
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-6 py-10 text-center text-gray-400">
                        گزارشی با این مشخصات یافت نشد.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
        <div class="px-6 py-4 border-t border-gray-100">{{ $reports->links() }}</div>
    </div>

    <!-- Modal تأیید حذف -->
    <div id="hs-delete-report-modal"
         class="hs-overlay hidden fixed inset-0 z-80 overflow-x-hidden overflow-y-auto"
         role="dialog" tabindex="-1" aria-labelledby="hs-delete-report-modal-label">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-md sm:w-full m-3 sm:mx-auto min-h-[calc(100%-3.5rem)] flex items-center">
            <div class="flex flex-col bg-white border shadow-sm rounded-xl overflow-hidden w-full">
                <!-- Header -->
                <div class="flex justify-between items-center py-3 px-4 border-b bg-red-50">
                    <div class="flex items-center gap-x-2">
                        <div class="flex-shrink-0 size-9 bg-red-100 rounded-full flex items-center justify-center">
                            <svg class="size-4 text-red-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
                                <path d="M12 9v4"/><path d="M12 17h.01"/>
                            </svg>
                        </div>
                        <h3 id="hs-delete-report-modal-label" class="font-bold text-gray-800">تأیید حذف گزارش</h3>
                    </div>
                    <button type="button"
                            class="flex justify-center items-center size-7 text-sm font-semibold rounded-full border border-transparent text-gray-800 hover:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none"
                            data-hs-overlay="#hs-delete-report-modal">
                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-5">
                    <p class="text-sm text-gray-700 mb-1">آیا از حذف این گزارش اطمینان دارید؟</p>
                    <p id="delete-report-label" class="text-sm font-semibold text-gray-900 mb-3"></p>
                    <p class="text-xs text-red-500">این عملیات برگشت‌پذیر نیست و گزارش به‌طور کامل حذف خواهد شد.</p>
                </div>

                <!-- Footer -->
                <div class="flex justify-end items-center gap-x-2 py-3 px-4 border-t bg-gray-50">
                    <button type="button"
                            data-hs-overlay="#hs-delete-report-modal"
                            class="py-2 px-4 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none">
                        انصراف
                    </button>
                    <form id="delete-report-form" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="py-2 px-4 inline-flex items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-red-500 text-white hover:bg-red-600 disabled:opacity-50 disabled:pointer-events-none">
                            <svg class="size-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/>
                                <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                            </svg>
                            بله، حذف کن
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openDeleteModal(url, label) {
            document.getElementById('delete-report-form').action = url;
            document.getElementById('delete-report-label').textContent = label;
            const modal = window.HSOverlay?.getInstance(document.getElementById('hs-delete-report-modal'));
            if (modal) {
                modal.open();
            } else if (window.HSOverlay) {
                HSOverlay.open(document.getElementById('hs-delete-report-modal'));
            } else {
                document.getElementById('hs-delete-report-modal').classList.remove('hidden');
            }
        }
    </script>
@endsection
