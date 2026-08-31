@extends('layouts.app')

@section('content')
<div class="mb-8">
    <h2 class="text-2xl font-bold text-gray-800">مدیریت دسته‌بندی: {{ $category->name }}</h2>
</div>

@if(session('success'))
    <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">{{ session('success') }}</div>
@endif

{{-- ردیف بالا: مشخصات + فرم افزودن فیلد --}}
<div class="grid lg:grid-cols-3 gap-6 mb-6">

    {{-- ستون چپ: مشخصات دسته --}}
    <div class="lg:col-span-1 space-y-4">
        <div class="bg-white border rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold mb-4 border-b pb-2">مشخصات دسته‌بندی</h3>
            <form action="{{ route('admin.categories.update', $category) }}" method="POST">
                @csrf @method('PUT')
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">عنوان</label>
                    <input type="text" name="name" value="{{ $category->name }}"
                           class="py-2 px-3 block w-full border border-gray-200 rounded-lg text-sm" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">ترتیب</label>
                    <input type="number" name="sort_order" value="{{ $category->sort_order }}"
                           class="py-2 px-3 block w-full border border-gray-200 rounded-lg text-sm">
                </div>
                <div class="mb-4 flex items-center">
                    <input type="checkbox" name="is_active" id="is_active" value="1"
                           {{ $category->is_active ? 'checked' : '' }}
                           class="shrink-0 border-gray-200 rounded text-blue-600">
                    <label for="is_active" class="text-sm ms-3">وضعیت فعال باشد</label>
                </div>
                <button type="submit" class="w-full py-2 px-3 bg-blue-600 text-white rounded-lg text-sm font-semibold">بروزرسانی</button>
            </form>
        </div>

        {{-- راهنما --}}
        @if(($detachedCount ?? 0) > 0)
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-4">
                <p class="text-sm font-semibold text-amber-900 mb-1">
                    ⚠️ {{ $detachedCount }} فیلد از درخت جدا افتاده‌اند
                </p>
                <p class="text-xs text-amber-800 leading-6">
                    این فیلدها در دیتابیس هستند ولی از هیچ فیلد سطح‌اولی به آن‌ها نمی‌رسیم،
                    پس نه اینجا دیده می‌شوند و نه ربات سراغشان می‌رود. معمولاً بازمانده‌ی
                    حذف یا جابه‌جایی قدیمی‌اند.
                </p>
                <p class="text-xs text-amber-800 mt-1">
                    برای دیدن فهرست: <code class="px-1 rounded bg-white/70" dir="ltr">php artisan fields:purge-orphans</code>
                </p>
            </div>
        @endif

        <div class="bg-white border rounded-xl shadow-sm p-4">
            <h4 class="text-sm font-semibold mb-3 text-gray-700">راهنمای انواع فیلد</h4>
            <div class="space-y-2 text-xs text-gray-600">
                <div class="flex items-start gap-2">
                    <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-600 shrink-0 font-medium">متن</span>
                    <span>کاربر متن آزاد وارد می‌کند</span>
                </div>
                <div class="flex items-start gap-2">
                    <span class="px-2 py-0.5 rounded bg-purple-100 text-purple-700 shrink-0 font-medium">گزینه</span>
                    <span>کاربر یکی را انتخاب می‌کند — مسیر متفاوت باز می‌شود (تو در تو)</span>
                </div>
                <div class="flex items-start gap-2">
                    <span class="px-2 py-0.5 rounded bg-blue-100 text-blue-700 shrink-0 font-medium">عکس</span>
                    <span>ارسال عکس (چندتایی = چند عکس)</span>
                </div>
                <div class="flex items-start gap-2">
                    <span class="px-2 py-0.5 rounded bg-green-100 text-green-700 shrink-0 font-medium">لینک</span>
                    <span>کاربر URL ارسال می‌کند</span>
                </div>
                <div class="flex items-start gap-2">
                    <span class="px-2 py-0.5 rounded bg-amber-100 text-amber-700 shrink-0 font-medium">تاریخ</span>
                    <span>انتخاب از تقویم شمسی (سال ← ماه ← روز) — تایپ نمی‌شود</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ستون راست: فرم افزودن فیلد سطح اول --}}
    <div class="lg:col-span-2">
        <div class="bg-gray-50 border rounded-xl shadow-sm p-5 h-full">
            <h3 class="text-base font-semibold mb-4 text-gray-800">افزودن فیلد سطح اول</h3>
            <form action="{{ route('admin.categories.fields.store', $category) }}" method="POST"
                  class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @csrf
                <div class="sm:col-span-2">
                    <label class="block text-sm mb-1 text-gray-600">عنوان سوال <span class="text-red-500">*</span></label>
                    <input type="text" name="label"
                           class="py-2 px-3 block w-full border border-gray-300 rounded-lg text-sm"
                           placeholder="مثلاً: نوع مشکل" required>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm mb-1 text-gray-600">توضیح راهنما (اختیاری)</label>
                    <input type="text" name="description"
                           class="py-2 px-3 block w-full border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm mb-1 text-gray-600">نوع ورودی <span class="text-xs text-gray-400">(کاربر چه می‌فرستد؟)</span></label>
                    <select name="type" class="py-2 px-3 block w-full border border-gray-300 rounded-lg text-sm">
                        <option value="text">متن آزاد</option>
                        <option value="option">گزینه (شاخه‌ای)</option>
                        <option value="photo">عکس</option>
                        <option value="link">لینک</option>
                        <option value="date">تاریخ (انتخاب از تقویم)</option>
                    </select>
                </div>
                <div id="root-date-range-wrap" class="hidden">
                    <label class="block text-xs font-medium text-gray-500 mb-1">محدوده تاریخ</label>
                    <select name="date_range" class="py-1.5 px-3 block w-full border border-gray-300 rounded-lg text-sm">
                        <option value="any">بدون محدودیت</option>
                        <option value="past">فقط گذشته (تا امروز)</option>
                        <option value="future">فقط آینده (از امروز)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm mb-1 text-gray-600">نوع زیرفیلد <span class="text-xs text-gray-400">(بعد از پاسخ، چه سوالی؟)</span></label>
                    <select name="child_type" id="root-child-type"
                            class="py-2 px-3 block w-full border border-gray-300 rounded-lg text-sm"
                            onchange="document.getElementById('root-child-label-wrap').classList.toggle('hidden', !this.value)">
                        <option value="">— بدون زیرفیلد —</option>
                        <option value="text">متن آزاد</option>
                        <option value="option">گزینه (شاخه‌ای)</option>
                        <option value="photo">عکس</option>
                        <option value="link">لینک</option>
                        <option value="date">تاریخ (انتخاب از تقویم)</option>
                    </select>
                </div>
                <div id="root-child-date-range-wrap" class="hidden">
                    <label class="block text-xs font-medium text-gray-500 mb-1">محدوده تاریخ زیرفیلد</label>
                    <select name="child_date_range" class="py-1.5 px-3 block w-full border border-gray-300 rounded-lg text-sm">
                        <option value="any">بدون محدودیت</option>
                        <option value="past">فقط گذشته (تا امروز)</option>
                        <option value="future">فقط آینده (از امروز)</option>
                    </select>
                </div>
                <div id="root-child-label-wrap" class="sm:col-span-2 hidden">
                    <label class="block text-sm mb-1 text-gray-600">عنوان زیرفیلد</label>
                    <input type="text" name="child_label"
                           class="py-2 px-3 block w-full border border-gray-300 rounded-lg text-sm"
                           placeholder="مثلاً: توضیحات بیشتر">
                </div>
                <div>
                    <label class="block text-sm mb-1 text-gray-600">ترتیب</label>
                    <input type="number" name="sort_order" value="{{ $category->fields->count() + 1 }}"
                           class="py-2 px-3 block w-full border border-gray-300 rounded-lg text-sm">
                </div>
                <div class="sm:col-span-2 flex gap-6">
                    <label class="flex items-center gap-2 cursor-pointer text-sm">
                        <input type="checkbox" name="is_required" value="1" checked
                               class="rounded border-gray-300 text-blue-600">اجباری
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer text-sm">
                        <input type="checkbox" name="is_multiple" value="1"
                               class="rounded border-gray-300 text-purple-600">چندتایی
                    </label>
                </div>
                <div class="sm:col-span-2">
                    <button type="submit"
                            class="w-full py-2 px-4 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700">
                        + افزودن فیلد سطح اول
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

{{-- ردیف پایین: درخت فیلدها — تمام عرض --}}
<div id="tree-container"
     class="bg-white border rounded-xl shadow-sm w-full"
     data-tree-url="{{ route('admin.categories.tree-fragment', $category) }}"
     data-category-id="{{ $category->id }}">
    @include('admin.categories._tree_fragment', ['category' => $category])
</div>
@endsection

{{-- Vtree edit popover --}}
<div id="vtree-popover"
     class="hidden fixed z-[999] bg-white border border-gray-200 rounded-xl shadow-2xl p-4 w-72"
     style="top:0;left:0;max-height:90vh;overflow-y:auto">

    {{-- Field edit panel --}}
    <div id="vp-field">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-bold text-gray-700">ویرایش فیلد</h4>
            <button onclick="vtreePopoverClose()" class="text-gray-400 hover:text-gray-600 text-lg leading-none">×</button>
        </div>
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">عنوان سوال</label>
                <input id="vp-f-label" type="text" placeholder="مثلاً: وضعیت ساختمان"
                       class="py-1.5 px-3 block w-full border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">نوع ورودی</label>
                <select id="vp-f-type" class="py-1.5 px-3 block w-full border border-gray-300 rounded-lg text-sm">
                    <option value="text">متن آزاد</option>
                    <option value="option">گزینه (شاخه‌ای)</option>
                    <option value="photo">عکس</option>
                    <option value="link">لینک</option>
                    <option value="date">تاریخ (تقویم)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">ترتیب در همین سطح</label>
                <div style="display:flex;gap:.4rem">
                    <button type="button" onclick="vtreeMoveField('up')"
                            style="flex:1;padding:.35rem;font-size:.75rem;border:1px solid #d1d5db;border-radius:.5rem;background:#fff;cursor:pointer">
                        ↑ بالاتر
                    </button>
                    <button type="button" onclick="vtreeMoveField('down')"
                            style="flex:1;padding:.35rem;font-size:.75rem;border:1px solid #d1d5db;border-radius:.5rem;background:#fff;cursor:pointer">
                        ↓ پایین‌تر
                    </button>
                </div>
            </div>
            <div id="vp-f-range-wrap" class="hidden">
                <label class="block text-xs font-medium text-gray-500 mb-1">محدوده تاریخ</label>
                <select id="vp-f-range" class="py-1.5 px-3 block w-full border border-gray-300 rounded-lg text-sm">
                    <option value="any">بدون محدودیت</option>
                    <option value="past">فقط گذشته (تا امروز)</option>
                    <option value="future">فقط آینده (از امروز)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">توضیح راهنما <span class="text-gray-400 font-normal">(اختیاری)</span></label>
                <input id="vp-f-desc" type="text" placeholder="توضیح کوتاه برای کاربر"
                       class="py-1.5 px-3 block w-full border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">ویژگی‌ها</label>
                <div class="flex gap-4 text-sm">
                    <label class="flex items-center gap-1.5 cursor-pointer text-gray-600">
                        <input type="checkbox" id="vp-f-required" class="rounded border-gray-300 text-blue-600"> اجباری
                    </label>
                    <label id="vp-f-multi-wrap" class="flex items-center gap-1.5 cursor-pointer text-gray-600">
                        <input type="checkbox" id="vp-f-multiple" class="rounded border-gray-300 text-purple-600"> چندتایی
                    </label>
                </div>
            </div>
        </div>
        <div class="flex gap-2 mt-4">
            <button onclick="vtreeSubmitField()"
                    class="flex-1 py-2 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700">ذخیره</button>
            <button onclick="vtreeDuplicateField()"
                    title="کپی این فیلد با همه زیرمجموعه‌هایش"
                    class="py-2 px-3 bg-indigo-50 text-indigo-600 text-xs font-semibold rounded-lg hover:bg-indigo-100">📋</button>
            <button onclick="vtreeDeleteField()"
                    class="py-2 px-3 bg-red-50 text-red-600 text-xs font-semibold rounded-lg hover:bg-red-100">حذف</button>
        </div>

        {{-- add always-child field --}}
        <div class="mt-3 pt-3 border-t border-gray-100">
            <button onclick="vtreeToggleAddAlwaysChild()"
                    class="w-full py-1 border border-dashed border-indigo-300 text-indigo-600 text-xs rounded-lg hover:bg-indigo-50 transition">
                + افزودن زیرفیلد همیشگی
            </button>
            <div id="vp-add-always-child" class="hidden mt-3 space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">عنوان زیرفیلد</label>
                    <input id="vp-ac-label" type="text" placeholder="مثلاً: توضیحات"
                           class="py-1.5 px-3 block w-full border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">نوع ورودی</label>
                    <select id="vp-ac-type" class="py-1.5 px-3 block w-full border border-gray-300 rounded-lg text-sm">
                        <option value="text">متن آزاد</option>
                        <option value="option">گزینه (شاخه‌ای)</option>
                        <option value="photo">عکس</option>
                        <option value="link">لینک</option>
                        <option value="date">تاریخ (تقویم)</option>
                    </select>
                </div>
                <div id="vp-ac-range-wrap" class="hidden">
                    <label class="block text-xs font-medium text-gray-500 mb-1">محدوده تاریخ</label>
                    <select id="vp-ac-range" class="py-1.5 px-3 block w-full border border-gray-300 rounded-lg text-sm">
                        <option value="any">بدون محدودیت</option>
                        <option value="past">فقط گذشته (تا امروز)</option>
                        <option value="future">فقط آینده (از امروز)</option>
                    </select>
                </div>
                <button onclick="vtreeStoreAlwaysChild()"
                        style="width:100%;padding:.4rem .75rem;background:#4f46e5;color:#fff;font-size:.75rem;font-weight:600;border-radius:.5rem;border:none;cursor:pointer">
                    + ثبت زیرفیلد همیشگی
                </button>
                <button onclick="document.getElementById('vp-add-always-child').classList.add('hidden');vtreeReposition()"
                        style="width:100%;padding:.25rem .75rem;font-size:.75rem;color:#9ca3af;border:1px solid #e5e7eb;border-radius:.5rem;background:#fff;cursor:pointer">
                    انصراف
                </button>
            </div>
        </div>

        {{-- add option (only for type=option fields) --}}
        <div id="vp-f-add-opt-wrap" class="hidden mt-4 pt-3 border-t border-gray-100">
            <button onclick="vtreeToggleAddOpt()"
                    class="w-full py-1 border border-dashed border-orange-300 text-orange-600 text-xs rounded-lg hover:bg-orange-50 transition">
                + افزودن گزینه
            </button>
            <div id="vp-add-opt" class="hidden mt-3 space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">عنوان گزینه</label>
                    <input id="vp-ao-label" type="text" placeholder="مثلاً: استیجاری"
                           class="py-1.5 px-3 block w-full border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">زیرفیلد بعد از این گزینه <span class="text-gray-400 font-normal">(اختیاری)</span></label>
                    <select id="vp-ao-child-type" class="py-1.5 px-3 block w-full border border-gray-300 rounded-lg text-sm">
                        <option value="">— بدون زیرفیلد —</option>
                        <option value="text">متن آزاد</option>
                        <option value="option">گزینه (شاخه‌ای)</option>
                        <option value="photo">عکس</option>
                        <option value="link">لینک</option>
                        <option value="date">تاریخ (تقویم)</option>
                    </select>
                </div>
                <div id="vp-ao-range-wrap" class="hidden">
                    <label class="block text-xs font-medium text-gray-500 mb-1">محدوده تاریخ</label>
                    <select id="vp-ao-range" class="py-1.5 px-3 block w-full border border-gray-300 rounded-lg text-sm">
                        <option value="any">بدون محدودیت</option>
                        <option value="past">فقط گذشته (تا امروز)</option>
                        <option value="future">فقط آینده (از امروز)</option>
                    </select>
                </div>
                <div id="vp-ao-child-label-wrap" class="hidden">
                    <label class="block text-xs font-medium text-gray-500 mb-1">عنوان زیرفیلد</label>
                    <input id="vp-ao-child-label" type="text" placeholder="مثلاً: آدرس ملک"
                           class="py-1.5 px-3 block w-full border border-gray-300 rounded-lg text-sm">
                </div>
                <button onclick="vtreeStoreOption()"
                        style="width:100%;padding:.4rem .75rem;background:#f97316;color:#fff;font-size:.75rem;font-weight:600;border-radius:.5rem;border:none;cursor:pointer">
                    + ثبت گزینه
                </button>
                <button onclick="document.getElementById('vp-add-opt').classList.add('hidden');vtreeReposition()"
                        style="width:100%;padding:.25rem .75rem;font-size:.75rem;color:#9ca3af;border:1px solid #e5e7eb;border-radius:.5rem;background:#fff;cursor:pointer">
                    انصراف
                </button>
            </div>
        </div>
    </div>

    {{-- Option edit panel --}}
    <div id="vp-option" class="hidden">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-bold text-gray-700">ویرایش گزینه</h4>
            <button onclick="vtreePopoverClose()" class="text-gray-400 hover:text-gray-600 text-lg leading-none">×</button>
        </div>
        <div class="mb-3">
            <label class="block text-xs font-medium text-gray-500 mb-1">عنوان گزینه</label>
            <input id="vp-o-label" type="text" placeholder="مثلاً: مالک"
                   class="py-1.5 px-3 block w-full border border-gray-300 rounded-lg text-sm">
        </div>
        <div class="flex gap-2">
            <button onclick="vtreeSubmitOption()"
                    class="flex-1 py-1.5 bg-orange-500 text-white text-xs font-semibold rounded-lg hover:bg-orange-600">ذخیره</button>
            <button onclick="vtreeCopyThisOption()"
                    title="کپی این گزینه با همه زیرمجموعه‌هایش برای paste در جای دیگر"
                    class="py-1.5 px-3 bg-indigo-50 text-indigo-600 text-xs font-semibold rounded-lg hover:bg-indigo-100">📋</button>
            <button onclick="vtreeDeleteOption()"
                    class="py-1.5 px-3 bg-red-50 text-red-600 text-xs font-semibold rounded-lg hover:bg-red-100">حذف</button>
        </div>
        {{-- add child field --}}
        <div class="mt-3 pt-3 border-t border-gray-100">
            <button onclick="vtreeToggleAddField()"
                    class="w-full py-1 border border-dashed border-indigo-300 text-indigo-600 text-xs rounded-lg hover:bg-indigo-50 transition">
                + افزودن زیرفیلد
            </button>
            <div id="vp-add-field" class="hidden mt-3 space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">عنوان فیلد</label>
                    <input id="vp-af-label" type="text" placeholder="مثلاً: آدرس ملک"
                           class="py-1.5 px-3 block w-full border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">نوع ورودی</label>
                    <select id="vp-af-type" class="py-1.5 px-3 block w-full border border-gray-300 rounded-lg text-sm">
                        <option value="text">متن آزاد</option>
                        <option value="option">گزینه (شاخه‌ای)</option>
                        <option value="photo">عکس</option>
                        <option value="link">لینک</option>
                        <option value="date">تاریخ (تقویم)</option>
                    </select>
                </div>
                <div id="vp-af-range-wrap" class="hidden">
                    <label class="block text-xs font-medium text-gray-500 mb-1">محدوده تاریخ</label>
                    <select id="vp-af-range" class="py-1.5 px-3 block w-full border border-gray-300 rounded-lg text-sm">
                        <option value="any">بدون محدودیت</option>
                        <option value="past">فقط گذشته (تا امروز)</option>
                        <option value="future">فقط آینده (از امروز)</option>
                    </select>
                </div>
                <div>
                    <label class="flex items-center gap-1.5 text-xs cursor-pointer text-gray-600">
                        <input type="checkbox" id="vp-af-required" checked class="rounded border-gray-300 text-blue-600">
                        اجباری
                    </label>
                </div>
                <button onclick="vtreeStoreField()"
                        style="width:100%;padding:.4rem .75rem;background:#4f46e5;color:#fff;font-size:.75rem;font-weight:600;border-radius:.5rem;border:none;cursor:pointer">
                    + ثبت فیلد
                </button>
                <button onclick="document.getElementById('vp-add-field').classList.add('hidden');vtreeReposition()"
                        style="width:100%;padding:.25rem .75rem;font-size:.75rem;color:#9ca3af;border:1px solid #e5e7eb;border-radius:.5rem;background:#fff;cursor:pointer">
                    انصراف
                </button>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<style>
/* ─── Visual Tree CSS ───────────────────────────────────────────── */
.vtree-wrap {
    padding: 2rem 3rem 3rem;
    overflow-x: auto;
    overflow-y: auto;
    min-height: 500px;
    max-height: 75vh;
    scrollbar-width: thin;
    scrollbar-color: #c7d2fe #e0e7ff;
    cursor: grab;
    background: #eef2ff;
    background-image: radial-gradient(#c7d2fe 1px, transparent 1px);
    background-size: 24px 24px;
}
.vtree-wrap::-webkit-scrollbar { height: 8px; }
.vtree-wrap::-webkit-scrollbar-track { background: #f3f4f6; border-radius: 4px; }
.vtree-wrap::-webkit-scrollbar-thumb { background: #c7d2fe; border-radius: 4px; }
.vtree-wrap::-webkit-scrollbar-thumb:hover { background: #818cf8; }

.vtree, .vtree ul {
    list-style: none; margin: 0; padding: 0;
    display: flex; justify-content: center; align-items: flex-start;
}
/* ul.vtree باید به اندازه محتوا باشد (نه 100% والد)
   تا scrollbar بتواند به هر دو طرف بپیماید */
ul.vtree {
    width: max-content;
    min-width: 100%;
}
/* فاصله بین کارت والد و خط افقی فرزندان */
.vtree ul {
    padding-top: 36px;
    position: relative;
}
/* خط عمودی از والد به سطر افقی فرزندان — centered دقیق */
.vtree ul::before {
    content: '';
    position: absolute; top: 0; left: 50%;
    transform: translateX(-1px);
    width: 2px; height: 36px; background: #d1d5db;
}
/* همه li‌ها */
.vtree li {
    display: flex; flex-direction: column; align-items: center;
    padding: 0 12px;
    position: relative;
}
/* فقط li‌های فرزند (نه ریشه، نه always-ul) — فضا برای stub و خط افقی */
.vtree ul:not(.vtree):not(.vtree-always-ul) > li {
    padding-top: 16px;
}
/* خطوط افقی بین برادرها — فقط در li‌های فرزند */
.vtree ul:not(.vtree):not(.vtree-always-ul) > li::before,
.vtree ul:not(.vtree):not(.vtree-always-ul) > li::after {
    content: ''; position: absolute; top: 0;
    border-top: 2px solid #d1d5db; width: 50%;
}
.vtree ul:not(.vtree):not(.vtree-always-ul) > li::before { right: 50%; }
.vtree ul:not(.vtree):not(.vtree-always-ul) > li::after  { left: 50%; }
.vtree ul:not(.vtree):not(.vtree-always-ul) > li:first-child::before,
.vtree ul:not(.vtree):not(.vtree-always-ul) > li:last-child::after { display: none; }
.vtree ul:not(.vtree):not(.vtree-always-ul) > li:only-child::before,
.vtree ul:not(.vtree):not(.vtree-always-ul) > li:only-child::after { display: none; }

/* ─── Nodes ─── */
.vtree-node {
    position: relative; z-index: 1;
    border: 1.5px solid; border-radius: 10px;
    text-align: center;
    width: 140px;           /* عرض ثابت → همتراز شدن خطوط */
    box-sizing: border-box;
    box-shadow: 0 2px 6px rgba(0,0,0,.09);
    padding: 7px 10px; cursor: default;
    transition: box-shadow .15s, transform .15s;
}
.vtree-node:hover { box-shadow: 0 4px 12px rgba(99,102,241,.25); transform: translateY(-1px); }
/* خط عمودی کوتاه از خط افقی به بالای هر کارت فرزند (stub)
   فقط برای uls عادی (نه vtree-always-ul و نه ریشه) */
.vtree ul:not(.vtree):not(.vtree-always-ul) > li > .vtree-node::before {
    content: '';
    position: absolute; top: -17px; left: 50%;
    transform: translateX(-1px);
    width: 2px; height: 17px; background: #d1d5db;
}
.vtree-option-node {
    border-radius: 99px !important;
    padding: 5px 12px !important;
    background: #fff7ed; border-color: #fb923c; color: #9a3412;
    width: 120px !important;
    box-sizing: border-box !important;
}
.vtree-badge  { display: block; font-size: 9px; opacity: .6; margin-bottom: 1px; }
.vtree-label  { display: block; font-size: 11px; font-weight: 600;
                overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* نوع فیلد → رنگ */
.vtree-type-option { background:#f5f3ff; border-color:#a78bfa; color:#5b21b6; }
.vtree-type-text   { background:#f9fafb; border-color:#9ca3af; color:#374151; }
.vtree-type-photo  { background:#eff6ff; border-color:#60a5fa; color:#1d4ed8; }
.vtree-type-link   { background:#f0fdf4; border-color:#4ade80; color:#15803d; }
.vtree-type-date   { background:#fffbeb; border-color:#fbbf24; color:#b45309; }

/* ─── Vtree hover highlight ─── */
.vtree-node:hover { filter: brightness(0.95); outline: 2px solid #6366f1; outline-offset: 1px; }

/* ─── Drag & Drop states ─── */
.vtree-node.vtree-dragging  { opacity: .4; }
.vtree-node.vtree-drop-ok   { outline: 2.5px dashed #22c55e !important; outline-offset: 3px; background-color: #f0fdf4 !important; transform: scale(1.05); }
.vtree-node.vtree-drop-no   { outline: 2px dashed #ef4444 !important; outline-offset: 2px; }
.vtree-node.vtree-selected       { outline: 2.5px solid #6366f1 !important; outline-offset: 2px; }
.vtree-node.vtree-field-selected { outline: 2.5px solid #dc2626 !important; outline-offset: 2px; background-color: #fef2f2 !important; }
.vtree-node.vtree-paste-target { outline: 2.5px solid #f59e0b !important; outline-offset: 3px; animation: vtree-pulse .8s infinite alternate; }
@keyframes vtree-pulse { from { box-shadow: 0 0 0 0 rgba(245,158,11,.4); } to { box-shadow: 0 0 0 8px rgba(245,158,11,0); } }

/* ─── Palette chips ─── */
.vtree-palette-chip {
    padding: .3rem .7rem; border-radius: .5rem; font-size: .72rem; font-weight: 600;
    cursor: grab; white-space: nowrap; transition: transform .1s, box-shadow .1s;
}
.vtree-palette-chip:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.12); }
.vtree-palette-chip:active { cursor: grabbing; }

/* ─── زیرفیلدهای همیشگی ─── */
/* specificity بالاتر برای override کردن .vtree ul (0,1,1) */
.vtree-wrap .vtree-always-ul {
    list-style: none; margin: 0; padding: 0 !important;
    padding-top: 4px !important;
    display: flex !important; flex-direction: column !important; align-items: center !important;
    justify-content: flex-start !important;
    position: static !important;
}
.vtree-wrap .vtree-always-ul::before { display: none !important; }
/* فقط فرزندِ مستقیم — نه هر li در عمق.
   با سلکتور descendant، یک ردیف گزینه که در عمقِ یک زیرفیلد همیشگی
   قرار می‌گرفت هم padding و هم خط افقی برادرهایش را از دست می‌داد
   و دکمه‌ها بدون خط اتصال معلق می‌ماندند. */
.vtree-wrap .vtree-always-ul > li {
    display: flex; flex-direction: column; align-items: center;
    padding: 0 !important; position: relative;
}
/* حذف خطوط horizontal connector فقط برای خودِ always-child li ها */
.vtree-wrap .vtree-always-ul > li::before,
.vtree-wrap .vtree-always-ul > li::after { display: none !important; }
.vtree-always-connector {
    font-size: 14px; color: #6366f1; line-height: 1;
    padding: 2px 0; user-select: none;
}
</style>

<script>
function toggleEdit(id) {
    const el = document.getElementById(id);
    if (el) el.classList.toggle('hidden');
}

// ─── AJAX tree refresh ───────────────────────────────────────────────────────
const treeContainer = document.getElementById('tree-container');
const TREE_URL = treeContainer?.dataset.treeUrl;
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content
          || document.querySelector('input[name="_token"]')?.value;

async function refreshTree() {
    if (!treeContainer || !TREE_URL) return;
    // ذخیره موقعیت scroll و zoom فعلی
    const wrap      = document.querySelector('.vtree-wrap');
    const savedLeft = wrap?.scrollLeft ?? 0;
    const savedTop  = wrap?.scrollTop  ?? 0;

    treeContainer.style.opacity = '0.5';
    try {
        const res  = await fetch(TREE_URL, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        treeContainer.innerHTML = data.html;
        // zoom فعلی را حفظ کن — refit نکن
        _applyTreeZoom();
        _updateUndoBtn();
        // موقعیت scroll را برگردان
        requestAnimationFrame(() => {
            const newWrap = document.querySelector('.vtree-wrap');
            if (newWrap) {
                newWrap.scrollLeft = savedLeft;
                newWrap.scrollTop  = savedTop;
            }
        });
    } finally {
        treeContainer.style.opacity = '1';
    }
}

// ─── Tree Zoom ────────────────────────────────────────────────────────────────
let _treeZoom = 0.8;

function vtreeZoom(delta) {
    _treeZoom = Math.max(0.25, Math.min(1.5, Math.round((_treeZoom + delta) * 10) / 10));
    _applyTreeZoom();
}

function vtreeZoomFit() {
    const wrap = document.querySelector('.vtree-wrap');
    const ul   = wrap?.querySelector('ul.vtree');
    if (!wrap || !ul) { _treeZoom = 0.8; _applyTreeZoom(); return; }

    // ابتدا zoom را به 1 برمی‌گردانیم تا عرض طبیعی را بخوانیم
    // حالا که ul.vtree دارای width:max-content است، scrollWidth عرض واقعی درخت را برمی‌گرداند
    ul.style.zoom = '1';
    const nat   = ul.scrollWidth;
    const avail = wrap.clientWidth - 40;

    if (nat > avail && nat > 0) {
        _treeZoom = Math.min(1.0, Math.max(0.15, Math.floor((avail / nat) * 100) / 100));
    } else {
        _treeZoom = 1.0;
    }
    _applyTreeZoom();

    // اسکرول به وسط درخت
    requestAnimationFrame(() => {
        wrap.scrollLeft = (wrap.scrollWidth - wrap.clientWidth) / 2;
    });
}

function _applyTreeZoom() {
    const ul = document.querySelector('.vtree-wrap ul.vtree');
    if (ul) ul.style.zoom = _treeZoom;
    const lbl = document.getElementById('vtree-zoom-label');
    if (lbl) lbl.textContent = Math.round(_treeZoom * 100) + '٪';
}

// ─── Undo History ────────────────────────────────────────────────────────────
const _history = [];
const MAX_UNDO  = 30;

function _pushUndo(action) {
    _history.push(action);
    if (_history.length > MAX_UNDO) _history.shift();
    _updateUndoBtn();
}

function _updateUndoBtn() {
    const btn = document.getElementById('vtree-undo-btn');
    if (!btn) return;
    const has = _history.length > 0;
    btn.disabled       = !has;
    btn.style.opacity  = has ? '1' : '0.4';
    btn.style.cursor   = has ? 'pointer' : 'default';
    btn.style.color    = has ? '#4f46e5' : '#6b7280';
    btn.style.borderColor = has ? '#a5b4fc' : '#e5e7eb';
    btn.title = has ? ('↩ ' + _history[_history.length - 1].label) : 'تغییری برای بازگشت وجود ندارد';
}

async function vtreeUndo() {
    if (_history.length === 0) return;
    const action = _history.pop();
    _updateUndoBtn();
    const catId = _catId();

    const doFetch = (url, method, body, json = false) => fetch(url, {
        method,
        body: json ? JSON.stringify(body) : body,
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': CSRF,
            ...(json ? { 'Content-Type': 'application/json' } : {}),
        },
    }).then(r => r.json());

    try {
        let ok = false;

        if (action.type === 'edit_field') {
            const fd = new FormData();
            fd.append('_method', 'PUT');
            fd.append('label',       action.old.label);
            fd.append('description', action.old.description ?? '');
            fd.append('type',        action.old.type);
            if (action.old.is_required) fd.append('is_required', '1');
            if (action.old.is_multiple) fd.append('is_multiple', '1');
            const d = await doFetch(`/admin/categories/${catId}/fields/${action.fieldId}`, 'POST', fd);
            ok = d.success;
        }

        else if (action.type === 'edit_option') {
            const fd = new FormData();
            fd.append('_method', 'PUT');
            fd.append('label', action.oldLabel);
            const d = await doFetch(`/admin/categories/${catId}/fields/${action.fieldId}/options/${action.optionId}`, 'POST', fd);
            ok = d.success;
        }

        else if (action.type === 'add_option') {
            const fd = new FormData(); fd.append('_method', 'DELETE');
            const d = await doFetch(`/admin/categories/${catId}/fields/${action.fieldId}/options/${action.optionId}`, 'POST', fd);
            ok = d.success;
        }

        else if (action.type === 'add_field') {
            const fd = new FormData(); fd.append('_method', 'DELETE');
            const d = await doFetch(`/admin/categories/${catId}/fields/${action.fieldId}`, 'POST', fd);
            ok = d.success;
        }

        // paste گزینه‌ها → حذف گزینه‌های تازه (حذف سرور بازگشتی است)
        else if (action.type === 'paste_options') {
            let allOk = true;
            for (const optId of action.optionIds) {
                const fd = new FormData(); fd.append('_method', 'DELETE');
                const d = await doFetch(`/admin/categories/${catId}/fields/${action.fieldId}/options/${optId}`, 'POST', fd);
                if (!d.success) allOk = false;
            }
            ok = allOk;
        }

        // paste فیلدها → حذف فیلدهای تازه
        else if (action.type === 'paste_fields') {
            let allOk = true;
            for (const fid of action.fieldIds) {
                const fd = new FormData(); fd.append('_method', 'DELETE');
                const d = await doFetch(`/admin/categories/${catId}/fields/${fid}`, 'POST', fd);
                if (!d.success) allOk = false;
            }
            ok = allOk;
        }

        // درج در زنجیره → اول بچه‌ها را به والد قبلی برگردان، بعد فیلد تازه را
        // حذف کن. ترتیب حیاتی است: حذف در سرور بازگشتی است و اگر بچه‌ها هنوز
        // زیرش باشند آن‌ها هم پاک می‌شوند.
        else if (action.type === 'insert_field') {
            let allOk = true;
            for (const cid of (action.movedChildIds || [])) {
                const d = await doFetch(`/admin/categories/${catId}/fields/${cid}/reparent`, 'PATCH',
                    { parent_option_id: null, parent_field_id: action.parentFieldId }, true);
                if (!d.success) allOk = false;
            }
            if (allOk) {
                const fd = new FormData(); fd.append('_method', 'DELETE');
                const d = await doFetch(`/admin/categories/${catId}/fields/${action.fieldId}`, 'POST', fd);
                allOk = d.success;
            } else {
                treeToast('❌ بازگرداندن زیرفیلدها ناموفق بود — فیلد حذف نشد', false);
            }
            ok = allOk;
        }

        else if (action.type === 'move_field') {
            const fd = new FormData();
            fd.append('direction', action.direction);
            const d = await doFetch(`/admin/categories/${catId}/fields/${action.fieldId}/move`, 'POST', fd);
            ok = d.success;
        }

        else if (action.type === 'reparent_field') {
            const d = await doFetch(`/admin/categories/${catId}/fields/${action.fieldId}/reparent`, 'PATCH',
                { parent_option_id: action.oldParentOptionId ?? null, parent_field_id: action.oldParentFieldId ?? null }, true);
            ok = d.success;
        }

        else if (action.type === 'reparent_option') {
            const d = await doFetch(`/admin/categories/${catId}/fields/${action.oldFieldId}/options/${action.optionId}/reparent`, 'PATCH',
                { field_id: action.oldFieldId }, true);
            ok = d.success;
        }

        if (ok) { treeToast('↩ بازگشت: ' + action.label); await refreshTree(); }
        else {
            treeToast('❌ بازگشت ناموفق', false);
            _history.push(action); _updateUndoBtn(); // برگردان به تاریخچه
        }
    } catch {
        treeToast('❌ خطا در بازگشت', false);
        _history.push(action); _updateUndoBtn();
    }
}

// Ctrl+Z / Cmd+Z
document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) {
        const active = document.activeElement;
        if (active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA')) return;
        e.preventDefault();
        vtreeUndo();
    }
});

// ─── Ctrl+Scroll → zoom ───────────────────────────────────────────────────────
document.addEventListener('wheel', e => {
    if (!e.ctrlKey) return;
    const wrap = e.target.closest('.vtree-wrap');
    if (!wrap) return;
    e.preventDefault();
    // نقطه‌ای که موس روی آن است را ثابت نگه‌داری می‌کنیم
    const rect   = wrap.getBoundingClientRect();
    const mouseX = e.clientX - rect.left + wrap.scrollLeft;
    const mouseY = e.clientY - rect.top  + wrap.scrollTop;
    const oldZoom = _treeZoom;
    const delta = e.deltaY < 0 ? 0.05 : -0.05;
    _treeZoom = Math.max(0.15, Math.min(1.5, Math.round((_treeZoom + delta) * 100) / 100));
    _applyTreeZoom();
    // تنظیم scroll تا نقطه موس ثابت بماند
    requestAnimationFrame(() => {
        const ratio = _treeZoom / oldZoom;
        wrap.scrollLeft = mouseX * ratio - (e.clientX - rect.left);
        wrap.scrollTop  = mouseY * ratio - (e.clientY - rect.top);
    });
}, { passive: false });

// ─── Click+Drag → pan ────────────────────────────────────────────────────────
let _pan = null;

document.addEventListener('mousedown', e => {
    if (e.button !== 0) return;
    const wrap = e.target.closest('.vtree-wrap');
    if (!wrap) return;
    // فقط روی پس‌زمینه (نه node یا دکمه)
    if (e.target.closest('.vtree-node, button, input, select, a')) return;
    _pan = { x: e.clientX, y: e.clientY, sl: wrap.scrollLeft, st: wrap.scrollTop, el: wrap };
    wrap.style.cursor = 'grabbing';
    wrap.style.userSelect = 'none';
    e.preventDefault();
});

document.addEventListener('mousemove', e => {
    if (!_pan) return;
    _pan.el.scrollLeft = _pan.sl - (e.clientX - _pan.x);
    _pan.el.scrollTop  = _pan.st - (e.clientY - _pan.y);
});

document.addEventListener('mouseup', () => {
    if (!_pan) return;
    _pan.el.style.cursor = '';
    _pan.el.style.userSelect = '';
    _pan = null;
});

document.addEventListener('mouseleave', () => {
    if (!_pan) return;
    _pan.el.style.cursor = '';
    _pan.el.style.userSelect = '';
    _pan = null;
});

function treeToast(msg, ok = true) {
    const old = document.getElementById('tree-toast');
    if (old) old.remove();
    const el = document.createElement('div');
    el.id = 'tree-toast';
    el.style.cssText = `position:fixed;bottom:1.5rem;left:1.5rem;z-index:9999;
        padding:.6rem 1.1rem;border-radius:.75rem;font-size:.85rem;font-weight:600;
        box-shadow:0 8px 24px rgba(0,0,0,.15);color:#fff;
        background:${ok ? '#10b981' : '#ef4444'};transition:opacity .3s`;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 2200);
}

// ─── Vtree node edit popover ────────────────────────────────────────────────
const _pop   = () => document.getElementById('vtree-popover');
const _catId = () => document.getElementById('tree-container')?.dataset.categoryId;

let _vpFieldId = null, _vpOptId = null, _vpOptFieldId = null;
let _vpAnchorEl = null; // element ای که popover روی آن باز شده

function vtreePopoverClose() { _pop().classList.add('hidden'); _vpAnchorEl = null; }

function vtreeReposition() {
    if (!_vpAnchorEl) return;
    requestAnimationFrame(() => _doPosition(_vpAnchorEl));
}

function _doPosition(el) {
    const pop  = _pop();
    const rect = el.getBoundingClientRect();
    const pw   = pop.offsetWidth  || 288;
    const ph   = pop.offsetHeight || 200;
    const vw   = window.innerWidth;
    const vh   = window.innerHeight;

    // سعی می‌کنیم زیر element باشد
    let left = rect.left + window.scrollX;
    let top  = rect.bottom + window.scrollY + 8;

    // اگر از راست بزند بیرون
    if (left + pw > vw - 8) left = vw - pw - 8;
    left = Math.max(8, left);

    // اگر از پایین بزند بیرون، بالای element نشان بده
    if (rect.bottom + ph + 16 > vh) {
        const topAbove = rect.top + window.scrollY - ph - 8;
        top = topAbove > window.scrollY + 8 ? topAbove : window.scrollY + Math.max(8, vh - ph - 12);
    }

    pop.style.left = left + 'px';
    pop.style.top  = top  + 'px';
}

function vtreePopoverShow(el) {
    _vpAnchorEl = el;
    const pop = _pop();
    pop.classList.remove('hidden');
    // position اولیه، بعد reposition واقعی بعد از render
    pop.style.left = '-9999px';
    pop.style.top  = '-9999px';
    requestAnimationFrame(() => _doPosition(el));
}

function vtreeEditField(el) {
    _vpFieldId = el.dataset.fieldId;
    document.getElementById('vp-f-label').value      = el.dataset.label || '';
    document.getElementById('vp-f-desc').value       = el.dataset.description || '';
    document.getElementById('vp-f-type').value       = el.dataset.type || 'text';
    document.getElementById('vp-f-range').value      = el.dataset.dateRange || 'any';
    document.getElementById('vp-f-range-wrap').classList.toggle('hidden', el.dataset.type !== 'date');
    document.getElementById('vp-f-required').checked = el.dataset.isRequired === '1';
    document.getElementById('vp-f-multiple').checked = el.dataset.isMultiple === '1';
    document.getElementById('vp-f-multi-wrap').style.display = el.dataset.type === 'option' ? 'none' : '';
    // نمایش بخش افزودن گزینه فقط برای فیلدهای نوع گزینه
    document.getElementById('vp-f-add-opt-wrap').classList.toggle('hidden', el.dataset.type !== 'option');
    document.getElementById('vp-add-opt').classList.add('hidden');
    document.getElementById('vp-ao-label').value = '';
    document.getElementById('vp-add-always-child').classList.add('hidden');
    document.getElementById('vp-ac-label').value = '';
    document.getElementById('vp-field').classList.remove('hidden');
    document.getElementById('vp-option').classList.add('hidden');
    vtreePopoverShow(el);
}

function vtreeEditOption(el) {
    _vpOptId      = el.dataset.optionId;
    _vpOptFieldId = el.dataset.fieldId;
    document.getElementById('vp-o-label').value = el.dataset.label || '';
    document.getElementById('vp-add-field').classList.add('hidden');
    document.getElementById('vp-af-label').value = '';
    document.getElementById('vp-option').classList.remove('hidden');
    document.getElementById('vp-field').classList.add('hidden');
    vtreePopoverShow(el);
}

function vtreeToggleAddAlwaysChild() {
    const el = document.getElementById('vp-add-always-child');
    el.classList.toggle('hidden');
    if (!el.classList.contains('hidden')) {
        document.getElementById('vp-ac-label').focus();
        requestAnimationFrame(() => vtreeReposition());
    }
}

async function vtreeStoreAlwaysChild() {
    const label = document.getElementById('vp-ac-label').value.trim();
    if (!label) { document.getElementById('vp-ac-label').focus(); return; }
    const catId = _catId();
    const fd = new FormData();
    fd.append('label',           label);
    fd.append('type',            document.getElementById('vp-ac-type').value);
    fd.append('date_range',      document.getElementById('vp-ac-range').value);
    fd.append('parent_field_id', _vpFieldId);
    fd.append('is_required',     '1');
    const res  = await fetch(`/admin/categories/${catId}/fields`, {
        method: 'POST', body: fd,
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
    });
    const data = await res.json();
    if (data.success) {
        if (data.field_id) _pushUndo({ type: 'add_field', fieldId: data.field_id, label: `افزودن زیرفیلد "${label}"` });
        vtreePopoverClose(); treeToast('✅ ' + data.message); await refreshTree();
    } else treeToast('❌ ' + (data.message || 'خطا'), false);
}

function vtreeToggleAddOpt() {
    const el = document.getElementById('vp-add-opt');
    el.classList.toggle('hidden');
    if (!el.classList.contains('hidden')) {
        document.getElementById('vp-ao-label').focus();
        document.getElementById('vp-ao-child-type').value = '';
        document.getElementById('vp-ao-child-label-wrap').classList.add('hidden');
        requestAnimationFrame(() => vtreeReposition());
    }
}

// نمایش/پنهان کردن فیلد عنوان child هنگام انتخاب نوع
document.addEventListener('change', e => {
    // فرم افزودن فیلد ریشه (select های name-based)
    if (e.target.name === 'type') {
        document.getElementById('root-date-range-wrap')
            .classList.toggle('hidden', e.target.value !== 'date');
    }
    if (e.target.name === 'child_type') {
        document.getElementById('root-child-date-range-wrap')
            .classList.toggle('hidden', e.target.value !== 'date');
    }

    // انتخاب محدوده فقط وقتی نوع «تاریخ» است دیده می‌شود
    const _rangeFor = {
        'vp-f-type': 'vp-f-range-wrap',
        'vp-ac-type': 'vp-ac-range-wrap',
        'vp-af-type': 'vp-af-range-wrap',
        'vp-ao-child-type': 'vp-ao-range-wrap',
    };
    if (_rangeFor[e.target.id]) {
        document.getElementById(_rangeFor[e.target.id])
            .classList.toggle('hidden', e.target.value !== 'date');
    }

    if (e.target.id === 'vp-ao-child-type') {
        const wrap = document.getElementById('vp-ao-child-label-wrap');
        wrap.classList.toggle('hidden', !e.target.value);
        if (e.target.value) {
            document.getElementById('vp-ao-child-label').value = '';
            document.getElementById('vp-ao-child-label').focus();
            requestAnimationFrame(() => vtreeReposition());
        }
    }
});

function vtreeToggleAddField() {
    const el = document.getElementById('vp-add-field');
    el.classList.toggle('hidden');
    if (!el.classList.contains('hidden')) {
        document.getElementById('vp-af-label').focus();
        requestAnimationFrame(() => vtreeReposition());
    }
}

async function vtreeStoreOption() {
    const label = document.getElementById('vp-ao-label').value.trim();
    if (!label) { document.getElementById('vp-ao-label').focus(); return; }
    const catId    = _catId();
    const childType  = document.getElementById('vp-ao-child-type').value;
    const childLabel = document.getElementById('vp-ao-child-label')?.value.trim();

    // ۱. ساخت گزینه
    const fd = new FormData(); fd.append('label', label);
    const res  = await fetch(`/admin/categories/${catId}/fields/${_vpFieldId}/options`, {
        method: 'POST', body: fd,
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
    });
    const data = await res.json();
    if (!data.success) { treeToast('❌ ' + (data.message || 'خطا'), false); return; }

    // ۲. اگر نوع child انتخاب شده، زیرفیلد هم بساز
    if (childType && data.option_id) {
        const fd2 = new FormData();
        fd2.append('label',            childLabel || label);
        fd2.append('type',             childType);
        fd2.append('date_range',       document.getElementById('vp-ao-range').value);
        fd2.append('is_required',      '1');
        fd2.append('parent_option_id', data.option_id);
        await fetch(`/admin/categories/${catId}/fields`, {
            method: 'POST', body: fd2,
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        });
    }

    _pushUndo({ type: 'add_option', fieldId: _vpFieldId, optionId: data.option_id, label: `افزودن گزینه "${label}"` });
    vtreePopoverClose();
    treeToast('✅ ' + data.message);
    await refreshTree();
}

async function vtreeStoreField() {
    const label = document.getElementById('vp-af-label').value.trim();
    if (!label) { document.getElementById('vp-af-label').focus(); return; }
    const catId = _catId();
    const fd = new FormData();
    fd.append('label', label);
    fd.append('type', document.getElementById('vp-af-type').value);
    fd.append('date_range', document.getElementById('vp-af-range').value);
    fd.append('parent_option_id', _vpOptId);
    if (document.getElementById('vp-af-required').checked) fd.append('is_required', '1');
    const res  = await fetch(`/admin/categories/${catId}/fields`, {
        method: 'POST', body: fd,
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
    });
    const data = await res.json();
    if (data.success) {
        if (data.field_id) _pushUndo({ type: 'add_field', fieldId: data.field_id, label: `افزودن فیلد "${label}"` });
        vtreePopoverClose(); treeToast('✅ ' + data.message); await refreshTree();
    } else treeToast('❌ ' + (data.message || 'خطا'), false);
}

async function vtreeSubmitField() {
    const catId = _catId();
    // ذخیره مقادیر قبلی برای undo
    const oldData = {
        label:       _vpAnchorEl?.dataset.label       ?? '',
        description: _vpAnchorEl?.dataset.description ?? '',
        type:        _vpAnchorEl?.dataset.type         ?? '',
        is_required: _vpAnchorEl?.dataset.isRequired  === '1',
        is_multiple: _vpAnchorEl?.dataset.isMultiple  === '1',
    };
    const fd = new FormData();
    fd.append('_method', 'PUT');
    fd.append('label',       document.getElementById('vp-f-label').value);
    fd.append('description', document.getElementById('vp-f-desc').value);
    fd.append('type',        document.getElementById('vp-f-type').value);
    fd.append('date_range',  document.getElementById('vp-f-range').value);
    if (document.getElementById('vp-f-required').checked) fd.append('is_required', '1');
    if (document.getElementById('vp-f-multiple').checked) fd.append('is_multiple', '1');
    const res  = await fetch(`/admin/categories/${catId}/fields/${_vpFieldId}`, {
        method: 'POST', body: fd,
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
    });
    const data = await res.json();
    if (data.success) {
        _pushUndo({ type: 'edit_field', fieldId: _vpFieldId, old: oldData, label: `ویرایش فیلد "${oldData.label}"` });
        vtreePopoverClose(); treeToast('✅ ' + data.message); await refreshTree();
    } else treeToast('❌ ' + (data.message || 'خطا'), false);
}

/**
 * جابه‌جایی فیلد بین برادرهایش. درگ فقط «زیرمجموعه کردن» است، پس تغییر
 * ترتیب راه جداگانه‌ای لازم داشت — نبودنش باعث شد کاربر درگ کند و
 * ناخواسته ساختار را تودرتو (و بعد حلقه‌دار) کند.
 */
async function vtreeMoveField(direction) {
    if (!_vpFieldId) return;
    const catId = _catId();
    const fd = new FormData();
    fd.append('direction', direction);

    const r = await _postJson(`/admin/categories/${catId}/fields/${_vpFieldId}/move`, fd);
    if (!r.ok) { treeToast('❌ ' + (r.message || 'خطا در جابه‌جایی'), false); return; }

    _pushUndo({
        type: 'move_field',
        fieldId: _vpFieldId,
        direction: direction === 'up' ? 'down' : 'up',
        label: 'تغییر ترتیب فیلد',
    });

    treeToast(direction === 'up' ? '✅ یک پله بالاتر رفت' : '✅ یک پله پایین‌تر رفت');
    vtreePopoverClose();
    await refreshTree();
}

async function vtreeDeleteField() {
    if (!confirm('این فیلد و تمام زیرمجموعه‌هایش حذف شوند؟')) return;
    const catId = _catId();
    const fd = new FormData(); fd.append('_method', 'DELETE');
    const res  = await fetch(`/admin/categories/${catId}/fields/${_vpFieldId}`, {
        method: 'POST', body: fd,
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
    });
    const data = await res.json();
    if (data.success) { vtreePopoverClose(); treeToast('✅ ' + data.message); await refreshTree(); }
    else treeToast('❌ ' + (data.message || 'خطا'), false);
}

// دکمه کپی در popover → ورود به paste mode (انتخاب مقصد)
function vtreeDuplicateField() {
    if (!_vpFieldId) return;
    _fieldClipboard = [_vpFieldId];
    vtreePopoverClose();
    _fieldPasteMode = true;
    _clearAllSelections();
    document.getElementById('palette-field-paste-section').style.display = 'flex';
    // فقط گره‌های فیلد؛ قبلاً گزینه‌ها هم نارنجی می‌شدند در حالی که کلیک روی آن‌ها
    // هیچ کاری نمی‌کرد — UI مقصدی را وعده می‌داد که وجود نداشت.
    _markTargets('.vtree-node[data-field-id]:not([data-option-id])');
    treeToast('روی فیلد مقصد کلیک کنید تا فیلد زیر آن paste شود');
}

async function vtreeSubmitOption() {
    const catId   = _catId();
    const oldLabel = _vpAnchorEl?.dataset.label ?? '';
    const fd = new FormData();
    fd.append('_method', 'PUT');
    fd.append('label', document.getElementById('vp-o-label').value);
    const res  = await fetch(`/admin/categories/${catId}/fields/${_vpOptFieldId}/options/${_vpOptId}`, {
        method: 'POST', body: fd,
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
    });
    const data = await res.json();
    if (data.success) {
        _pushUndo({ type: 'edit_option', fieldId: _vpOptFieldId, optionId: _vpOptId, oldLabel, label: `ویرایش گزینه "${oldLabel}"` });
        vtreePopoverClose(); treeToast('✅ ' + data.message); await refreshTree();
    } else treeToast('❌ ' + (data.message || 'خطا'), false);
}

async function vtreeDeleteOption() {
    if (!confirm('این گزینه و تمام زیرفیلدهایش حذف شوند؟')) return;
    const catId = _catId();
    const fd = new FormData(); fd.append('_method', 'DELETE');
    const res  = await fetch(`/admin/categories/${catId}/fields/${_vpOptFieldId}/options/${_vpOptId}`, {
        method: 'POST', body: fd,
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
    });
    const data = await res.json();
    if (data.success) { vtreePopoverClose(); treeToast('✅ ' + data.message); await refreshTree(); }
    else treeToast('❌ ' + (data.message || 'خطا'), false);
}

// بستن popover با کلیک بیرون از آن
document.addEventListener('click', e => {
    const pop = _pop();
    if (!pop.classList.contains('hidden') && !pop.contains(e.target) && !e.target.closest('.vtree-node')) {
        vtreePopoverClose();
    }
});

// ─── کمک‌تابع‌های مشترک paste ────────────────────────────────────────────────

/**
 * POST + خواندن امن پاسخ.
 * res.json() روی پاسخ غیر-JSON (۴۱۹ انقضای CSRF، ۵۰۰ با بدنه HTML) throw می‌کند؛
 * اگر آن throw گرفته نشود کاربر هیچ پیامی نمی‌بیند و فکر می‌کند کار انجام شده.
 * خروجی همیشه { ok, message, data } است.
 */
async function _postJson(url, fd) {
    let res;
    try {
        res = await fetch(url, {
            method: 'POST', body: fd,
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        });
    } catch (err) {
        console.error('[paste] network', err);
        return { ok: false, message: 'ارتباط با سرور برقرار نشد' };
    }

    if (res.status === 419) return { ok: false, message: 'نشست منقضی شده — صفحه را رفرش کنید' };

    let data = null;
    try { data = await res.json(); } catch (err) { /* بدنه JSON نبود */ }

    if (!res.ok)  return { ok: false, message: (data && data.message) || `خطای سرور ${res.status}` };
    if (!data)    return { ok: false, message: 'پاسخ سرور قابل خواندن نبود' };

    return { ok: data.success !== false, message: data.message || '', data };
}

/** هر ورود به حالت paste باید هر دو نوع انتخاب و هایلایت‌هایشان را پاک کند */
function _clearAllSelections() {
    vtreeClearSelection();
    vtreeClearFieldSelection();
}

/** هایلایت مقصدهای مجاز — sel تعیین می‌کند چه چیزی واقعاً قابل کلیک است */
function _markTargets(sel) {
    document.querySelectorAll(sel).forEach(n => n.classList.add('vtree-paste-target'));
}

function _unmarkTargets() {
    document.querySelectorAll('.vtree-paste-target').forEach(n => n.classList.remove('vtree-paste-target'));
}

// ─── Drag & Drop + Multi-select ─────────────────────────────────────────────
let _dndSource        = null;  // { kind:'palette'|'field'|'option', type?, fieldId?, optionId?, ownerFieldId? }
let _selected         = [];    // [{ kind, id }]  — multi-select گزینه‌ها
let _selectedFields   = [];    // [{ kind, id }]  — multi-select فیلدها (Ctrl+Click)
let _pasteMode        = false;
let _clipboard        = [];    // option_ids to paste
let _fieldPasteMode   = false;
let _fieldClipboard   = [];    // field_ids to paste as always-children

// ── Click handler (click vs ctrl+click vs paste-click)
function vtreeNodeClick(e, el, kind) {
    e.stopPropagation();

    // paste mode — option paste
    if (_pasteMode) {
        if (kind === 'field' && el.dataset.type === 'option') {
            vtreePasteHere(el);
        } else if (kind === 'field') {
            treeToast('❌ گزینه فقط زیر فیلدی از نوع «گزینه» می‌نشیند. این فیلد از نوع دیگری است.', false);
        } else if (kind === 'option') {
            // فقط فرزندِ مستقیم؛ سلکتور قبلی descendant بود و می‌توانست یک نوه را
            // به‌عنوان مقصد بردارد و paste را در عمق اشتباه بنشاند.
            const li = el.closest('li');
            const childField = li?.querySelector(':scope > ul > li > .vtree-node[data-type="option"]');
            if (childField) {
                vtreePasteHere(childField);
            } else {
                treeToast('❌ این گزینه، فیلد شاخه‌ای ندارد. ابتدا یک فیلد از نوع «گزینه» زیر آن بسازید.', false);
            }
        }
        return;
    }

    // paste mode — field paste (always-child)
    if (_fieldPasteMode) {
        if (kind === 'field') {
            vtreePasteFieldsHere(el);
        } else {
            treeToast('❌ مقصد باید یک فیلد باشد، نه گزینه.', false);
        }
        return;
    }

    // ctrl/cmd + click = toggle selection
    if ((e.ctrlKey || e.metaKey) && kind === 'option') {
        e.preventDefault();
        vtreeToggleSelect(el);
        return;
    }
    // ctrl/cmd + click روی فیلد → انتخاب/لغو انتخاب (بدون باز شدن popover)
    if ((e.ctrlKey || e.metaKey) && kind === 'field') {
        e.preventDefault();
        vtreeToggleFieldSelect(el);
        return;
    }

    // normal click = open edit popover
    if (kind === 'field')  vtreeEditField(el);
    else                   vtreeEditOption(el);
}

function vtreeToggleSelect(el) {
    const id = el.dataset.optionId;
    const idx = _selected.findIndex(s => s.id === id);
    if (idx >= 0) {
        _selected.splice(idx, 1);
        el.classList.remove('vtree-selected');
    } else {
        _selected.push({ kind: 'option', id });
        el.classList.add('vtree-selected');
    }
    _updatePaletteCopySection();
}

// کپی مستقیم از popover — فقط همین یک گزینه
function vtreeCopyThisOption() {
    vtreePopoverClose();
    _clipboard  = [_vpOptId];
    _pasteMode  = true;
    // _selected = [] تنها آرایه را خالی می‌کرد و کلاس‌های .vtree-selected
    // روی گره‌ها می‌ماندند؛ شمارنده با چیزی که کاربر می‌دید نمی‌خواند.
    _clearAllSelections();
    document.getElementById('palette-copy-section').style.display = 'none';
    document.getElementById('palette-paste-section').style.display = 'flex';
    _markTargets('.vtree-node[data-type="option"]');
    treeToast('روی یک فیلد گزینه‌ای کلیک کنید تا گزینه آنجا paste شود');
}

function vtreeClearSelection() {
    _selected = [];
    document.querySelectorAll('.vtree-selected').forEach(n => n.classList.remove('vtree-selected'));
    _updatePaletteCopySection();
}

// ─── انتخاب چندتایی فیلدها با Ctrl+Click ───────────────────────────────────
function vtreeToggleFieldSelect(el) {
    const id  = el.dataset.fieldId;
    const idx = _selectedFields.findIndex(s => s.id === id);
    if (idx >= 0) {
        _selectedFields.splice(idx, 1);
        el.classList.remove('vtree-field-selected');
    } else {
        _selectedFields.push({ kind: 'field', id });
        el.classList.add('vtree-field-selected');
    }
    _updatePaletteFieldSection();
}

function vtreeClearFieldSelection() {
    _selectedFields = [];
    document.querySelectorAll('.vtree-field-selected').forEach(n => n.classList.remove('vtree-field-selected'));
    _updatePaletteFieldSection();
}

function _updatePaletteFieldSection() {
    const sec = document.getElementById('palette-field-section');
    const cnt = document.getElementById('palette-field-count');
    if (!sec) return;
    if (_selectedFields.length > 0) {
        sec.style.display = 'flex';
        cnt.textContent   = _selectedFields.length + ' فیلد انتخابی';
    } else {
        sec.style.display = 'none';
    }
}

// ورود به حالت paste برای فیلدهای انتخابی
function vtreeEnterFieldPasteMode() {
    if (_selectedFields.length === 0) return;
    // مرتب‌سازی بر اساس ترتیب ظهور در درخت (نه ترتیب کلیک)
    const allNodes = [...document.querySelectorAll('.vtree-node[data-field-id]')];
    _fieldClipboard = _selectedFields
        .map(s => s.id)
        .sort((a, b) => {
            const ia = allNodes.findIndex(n => n.dataset.fieldId === a);
            const ib = allNodes.findIndex(n => n.dataset.fieldId === b);
            return ia - ib;
        });
    _clearAllSelections();
    _fieldPasteMode = true;
    document.getElementById('palette-field-section').style.display      = 'none';
    document.getElementById('palette-field-paste-section').style.display = 'flex';
    // فقط گره‌های فیلد قابل کلیک‌اند، پس فقط همان‌ها هایلایت می‌شوند
    _markTargets('.vtree-node[data-field-id]:not([data-option-id])');
    treeToast('روی فیلد مقصد کلیک کنید تا فیلدها به عنوان زیرفیلد همیشگی paste شوند');
}

function vtreeCancelFieldPaste() {
    _fieldPasteMode = false;
    _fieldClipboard = [];
    document.getElementById('palette-field-paste-section').style.display = 'none';
    _unmarkTargets();
}

async function vtreePasteFieldsHere(targetEl) {
    const targetFieldId = targetEl.dataset.fieldId;
    const catId         = _catId();
    _unmarkTargets();
    document.getElementById('palette-field-paste-section').style.display = 'none';
    _fieldPasteMode = false;

    const ids = [..._fieldClipboard];
    _fieldClipboard = [];

    let count = 0;
    let firstError = '';
    const createdIds = [];
    for (let i = 0; i < ids.length; i++) {
        const fd = new FormData();
        fd.append('parent_field_id', targetFieldId);
        // paste_index حذف شد: سرور خودش max(sort_order)+1 می‌گیرد و چون
        // درخواست‌ها ترتیبی‌اند، ترتیب حفظ می‌شود.
        const r = await _postJson(`/admin/categories/${catId}/fields/${ids[i]}/duplicate`, fd);
        if (r.ok) {
            count++;
            if (r.data && r.data.field_id) createdIds.push(r.data.field_id);
        } else if (!firstError) firstError = r.message;
    }

    // حتی وقتی بخشی موفق شده، همان بخش باید قابل بازگشت باشد
    if (createdIds.length) {
        _pushUndo({
            type: 'paste_fields',
            fieldIds: createdIds,
            label: `paste ${createdIds.length} فیلد`,
        });
    }

    // قبلاً حتی وقتی همه شکست می‌خوردند «✅ 0 فیلد paste شد» نشان داده می‌شد
    if (count === 0) {
        treeToast('❌ ' + (firstError || 'هیچ فیلدی paste نشد'), false);
    } else if (count < ids.length) {
        treeToast(`⚠️ ${count} از ${ids.length} فیلد paste شد — ${firstError}`, false);
    } else {
        treeToast(`✅ ${count} فیلد به عنوان زیرفیلد همیشگی paste شد`);
    }
    await refreshTree();
}

/** شمارش گره‌های زیر یک فیلد در درخت رندرشده — برای پیام تأیید */
function _countSubtree(fieldId) {
    const el = document.querySelector(`.vtree-node[data-field-id="${fieldId}"]:not([data-option-id])`);
    const li = el?.closest('li');
    if (!li) return { fields: 1, options: 0 };
    return {
        fields:  li.querySelectorAll('.vtree-node[data-field-id]:not([data-option-id])').length,
        options: li.querySelectorAll('.vtree-node[data-option-id]').length,
    };
}

async function vtreeDeleteSelectedFields() {
    if (_selectedFields.length === 0) return;

    const ids = _selectedFields.map(s => s.id);

    // حذف بازگشتی است و undo ندارد، پس کاربر باید دقیقاً بداند چه از دست می‌رود
    let fields = 0, options = 0;
    for (const id of ids) {
        const c = _countSubtree(id);
        fields  += c.fields;
        options += c.options;
    }

    const extra = fields - ids.length;
    let msg = `${ids.length} فیلد انتخاب شده است.`;
    if (extra > 0 || options > 0) {
        const parts = [];
        if (extra > 0)   parts.push(`${extra} زیرفیلد`);
        if (options > 0) parts.push(`${options} گزینه`);
        msg += `\nبا زیرمجموعه‌هایشان ${parts.join(' و ')} هم حذف می‌شود.`;
    }
    msg += '\n\nاین کار قابل بازگشت نیست. ادامه می‌دهید؟';

    if (!confirm(msg)) return;

    const catId = _catId();
    vtreeClearFieldSelection();

    let done = 0, firstError = '';
    for (const id of ids) {
        const fd = new FormData(); fd.append('_method', 'DELETE');
        const r = await _postJson(`/admin/categories/${catId}/fields/${id}`, fd);
        if (r.ok) done++;
        else if (!firstError) firstError = r.message;
    }

    if (done === 0)            treeToast('❌ ' + (firstError || 'حذف انجام نشد'), false);
    else if (done < ids.length) treeToast(`⚠️ ${done} از ${ids.length} فیلد حذف شد — ${firstError}`, false);
    else                        treeToast(`✅ ${done} فیلد حذف شد`);

    await refreshTree();
}

function _updatePaletteCopySection() {
    const sec  = document.getElementById('palette-copy-section');
    const cnt  = document.getElementById('palette-sel-count');
    if (_selected.length > 0) {
        sec.style.display = 'flex';
        cnt.textContent   = _selected.length + ' گزینه انتخابی';
    } else {
        sec.style.display = 'none';
    }
}

function vtreeCopySelected() {
    _clipboard  = _selected.map(s => s.id);
    _pasteMode  = true;
    _clearAllSelections();
    document.getElementById('palette-copy-section').style.display = 'none';
    document.getElementById('palette-paste-section').style.display = 'flex';
    _markTargets('.vtree-node[data-type="option"]');
    treeToast('روی یک فیلد گزینه‌ای کلیک کنید تا paste شود', true);
}

function vtreeCancelPaste() {
    _pasteMode = false;
    _clipboard = [];
    document.getElementById('palette-paste-section').style.display = 'none';
    _unmarkTargets();
}

async function vtreePasteHere(el) {
    const catId   = _catId();
    const fieldId = el.dataset.fieldId;
    _unmarkTargets();
    document.getElementById('palette-paste-section').style.display = 'none';
    _pasteMode = false;

    const fd = new FormData();
    _clipboard.forEach(id => fd.append('option_ids[]', id));
    _clipboard = [];

    const r = await _postJson(`/admin/categories/${catId}/fields/${fieldId}/options/batch-copy`, fd);
    if (r.ok) {
        // شناسه‌های تازه را برای undo نگه می‌داریم؛ حذف سمت سرور بازگشتی است
        // پس پاک کردن گزینه‌ی سطح‌اول کل زیردرخت کپی‌شده را با خود می‌برد.
        const newIds = (r.data && r.data.option_ids) || [];
        if (newIds.length) {
            _pushUndo({
                type: 'paste_options',
                fieldId,
                optionIds: newIds,
                label: `paste ${newIds.length} گزینه`,
            });
        }
        treeToast('✅ ' + (r.message || 'گزینه‌ها کپی شدند'));
        await refreshTree();
    } else {
        treeToast('❌ ' + (r.message || 'خطا در paste'), false);
    }
}

// ── Palette instant-create helpers ───────────────────────────────────────────

async function _paletteCreateOption(fieldId) {
    const catId = _catId();
    const label = 'گزینه جدید';
    const fd = new FormData();
    fd.append('label', label);
    const res  = await fetch(`/admin/categories/${catId}/fields/${fieldId}/options`, {
        method: 'POST', body: fd,
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
    });
    const data = await res.json();
    if (!data.success) { treeToast('❌ خطا در ایجاد گزینه', false); return; }
    _pushUndo({ type: 'add_option', fieldId, optionId: data.option_id, label: `افزودن "${label}"` });
    treeToast('✅ گزینه ساخته شد — عنوان را ویرایش کنید');
    await refreshTree();
    // باز کردن popover ویرایش روی گزینه جدید
    requestAnimationFrame(() => {
        const el = document.querySelector(`.vtree-node[data-option-id="${data.option_id}"]`);
        if (el) { el.scrollIntoView({ block: 'nearest', inline: 'nearest' }); vtreeEditOption(el); }
    });
}

async function _paletteCreateField(parentOptionId, type) {
    const catId  = _catId();
    const labels = { text: 'فیلد متنی', option: 'فیلد گزینه‌ای', photo: 'فیلد عکس', link: 'فیلد لینک', date: 'فیلد تاریخ' };
    const label  = labels[type] || 'فیلد جدید';
    const fd = new FormData();
    fd.append('label', label);
    fd.append('type', type);
    fd.append('parent_option_id', parentOptionId);
    fd.append('is_required', '1');
    const res  = await fetch(`/admin/categories/${catId}/fields`, {
        method: 'POST', body: fd,
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
    });
    const data = await res.json();
    if (!data.success) { treeToast('❌ خطا در ایجاد فیلد', false); return; }
    if (data.field_id) _pushUndo({ type: 'add_field', fieldId: data.field_id, label: `افزودن "${label}"` });
    treeToast('✅ فیلد ساخته شد — عنوان را ویرایش کنید');
    await refreshTree();
    // باز کردن popover ویرایش روی فیلد جدید
    requestAnimationFrame(() => {
        const el = document.querySelector(`.vtree-node[data-field-id="${data.field_id}"]`);
        if (el) { el.scrollIntoView({ block: 'nearest', inline: 'nearest' }); vtreeEditField(el); }
    });
}

async function _paletteCreateAlwaysChildField(parentFieldId, type) {
    try {
        const catId  = _catId();
        const labels = { text: 'فیلد متنی', option: 'فیلد گزینه‌ای', photo: 'فیلد عکس', link: 'فیلد لینک', date: 'فیلد تاریخ' };
        const label  = labels[type] || 'فیلد جدید';
        const fd = new FormData();
        fd.append('label', label);
        fd.append('type', type);
        fd.append('parent_field_id', parentFieldId);
        fd.append('is_required', '1');
        const res  = await fetch(`/admin/categories/${catId}/fields`, {
            method: 'POST', body: fd,
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
        });
        if (!res.ok) { treeToast(`❌ خطای سرور ${res.status}`, false); return; }
        const data = await res.json();
        if (!data.success) { treeToast('❌ ' + (data.message || 'خطا در ایجاد فیلد'), false); return; }
        if (data.field_id) _pushUndo({ type: 'add_field', fieldId: data.field_id, label: `افزودن "${label}"` });
        treeToast('✅ زیرفیلد همیشگی ساخته شد — عنوان را ویرایش کنید');
        await refreshTree();
        requestAnimationFrame(() => {
            const el = document.querySelector(`.vtree-node[data-field-id="${data.field_id}"]`);
            if (el) { el.scrollIntoView({ block: 'nearest', inline: 'nearest' }); vtreeEditField(el); }
            else treeToast('⚠️ فیلد ساخته شد ولی در درخت یافت نشد — صفحه را رفرش کنید', false);
        });
    } catch (err) {
        treeToast('❌ خطا: ' + err.message, false);
        console.error('[always-child] error:', err);
    }
}

/** زیرفیلدهای همیشگیِ یک گره، از روی DOM — بدون رفت‌وبرگشت با سرور */
function _alwaysChildNodes(el) {
    const li = el.closest('li');
    if (!li) return [];
    const ul = li.querySelector(':scope > ul.vtree-always-ul');
    if (!ul) return [];
    return [...ul.querySelectorAll(':scope > li > .vtree-node')];
}

/** دیالوگ انتخاب: شاخه‌ی موازی یا درج در زنجیره؟ → 'chain' | 'branch' | null */
function _askInsertMode(targetLabel) {
    return new Promise(resolve => {
        const back = document.createElement('div');
        back.style.cssText = 'position:fixed;inset:0;z-index:10000;background:rgba(17,24,39,.45);' +
            'display:flex;align-items:center;justify-content:center';

        const btn = 'display:block;width:100%;text-align:right;padding:.6rem .8rem;margin-bottom:.5rem;' +
            'border-radius:.6rem;font-size:.8rem;font-weight:600;cursor:pointer;font-family:inherit;line-height:1.7';

        back.innerHTML =
            '<div dir="rtl" style="background:#fff;border-radius:1rem;padding:1.25rem;max-width:27rem;width:90%;' +
                 'box-shadow:0 20px 50px rgba(0,0,0,.25);font-family:inherit">' +
              '<p id="_im-title" style="font-size:.88rem;font-weight:700;color:#111827;margin:0 0 .3rem"></p>' +
              '<p style="font-size:.75rem;color:#6b7280;line-height:1.9;margin:0 0 1rem">' +
                'فیلد تازه را کجا بگذارم؟</p>' +
              '<button data-mode="chain" style="' + btn + ';border:1.5px solid #6366f1;background:#eef2ff;color:#3730a3">' +
                '⛓ در زنجیره — بین این فیلد و زیرفیلد فعلی‌اش' +
              '</button>' +
              '<button data-mode="branch" style="' + btn + ';border:1.5px solid #e5e7eb;background:#fff;color:#374151">' +
                '⑂ شاخه‌ی موازی — کنار زیرفیلد فعلی' +
              '</button>' +
              '<button data-mode="cancel" style="' + btn + ';border:none;background:transparent;color:#9ca3af;margin:0;text-align:center">' +
                'انصراف' +
              '</button>' +
            '</div>';

        // عنوان کاربر است، نه HTML — با textContent می‌نشیند تا تزریق نشود
        back.querySelector('#_im-title').textContent = '«' + targetLabel + '» از قبل زیرفیلد همیشگی دارد';

        const done = mode => { back.remove(); document.removeEventListener('keydown', onKey); resolve(mode); };
        const onKey = e => { if (e.key === 'Escape') done(null); };

        back.addEventListener('click', e => {
            if (e.target === back) return done(null);
            const b = e.target.closest('button[data-mode]');
            if (!b) return;
            done(b.dataset.mode === 'cancel' ? null : b.dataset.mode);
        });
        document.addEventListener('keydown', onKey);
        document.body.appendChild(back);
    });
}

/** فیلد تازه را وسط زنجیره می‌نشاند: والد → تازه → زیرفیلدهای قبلیِ والد */
async function _paletteInsertInChain(parentFieldId, type) {
    const catId  = _catId();
    const labels = { text: 'فیلد متنی', option: 'فیلد گزینه‌ای', photo: 'فیلد عکس', link: 'فیلد لینک', date: 'فیلد تاریخ' };
    const label  = labels[type] || 'فیلد جدید';

    const fd = new FormData();
    fd.append('label', label);
    fd.append('type', type);
    fd.append('is_required', '1');

    const r = await _postJson(`/admin/categories/${catId}/fields/${parentFieldId}/insert-in-chain`, fd);
    if (!r.ok) { treeToast('❌ ' + (r.message || 'خطا در درج فیلد'), false); return; }

    _pushUndo({
        type: 'insert_field',
        fieldId: r.data.field_id,
        parentFieldId,
        movedChildIds: r.data.moved_ids || [],
        label: `درج "${label}" در زنجیره`,
    });

    treeToast('✅ فیلد در زنجیره درج شد — عنوان را ویرایش کنید');
    await refreshTree();
    requestAnimationFrame(() => {
        const el = document.querySelector(`.vtree-node[data-field-id="${r.data.field_id}"]:not([data-option-id])`);
        if (el) { el.scrollIntoView({ block: 'nearest', inline: 'nearest' }); vtreeEditField(el); }
        else treeToast('⚠️ فیلد ساخته شد ولی در درخت یافت نشد — صفحه را رفرش کنید', false);
    });
}

// ── Palette drag
function vtreePaletteDrag(e, type) {
    _dndSource = { kind: 'palette', type };
    e.dataTransfer.effectAllowed = 'copy';
}

// ── Node drag
function vtreeDragStart(e, kind, el) {
    e.stopPropagation();
    if (kind === 'field') {
        _dndSource = {
            kind: 'field',
            fieldId: el.dataset.fieldId,
            type: el.dataset.type,
            oldParentOptionId: el.dataset.parentOptionId || null,
            oldParentFieldId:  el.dataset.parentFieldId  || null,
        };
    } else {
        _dndSource = { kind: 'option', optionId: el.dataset.optionId, ownerFieldId: el.dataset.fieldId };
    }
    e.dataTransfer.effectAllowed = 'move';
    setTimeout(() => el.classList.add('vtree-dragging'), 0);
}

// ── Drag over
function vtreeDragOver(e, el) {
    e.preventDefault();
    e.stopPropagation();
    if (!_dndSource) return;
    const ok = _dropOk(el);
    el.classList.toggle('vtree-drop-ok', ok);
    el.classList.toggle('vtree-drop-no', !ok);
    e.dataTransfer.dropEffect = ok ? (_dndSource.kind === 'palette' ? 'copy' : 'move') : 'none';
}

function vtreeDragLeave(e, el) {
    el.classList.remove('vtree-drop-ok', 'vtree-drop-no');
}

function _dropOk(el) {
    if (!_dndSource) return false;
    const isField  = !!el.dataset.fieldId && !el.dataset.optionId;
    const isOption = !!el.dataset.optionId;
    const fType    = el.dataset.type;

    if (_dndSource.kind === 'palette') {
        // palette → روی option: افزودن child field
        // palette → روی field (type=option): افزودن option
        // palette → روی field (هر نوع): افزودن زیرفیلد همیشگی
        return isOption || isField;
    }
    if (_dndSource.kind === 'field') {
        // field → روی option (جابجایی) یا روی field دیگر (تبدیل به always-child)
        const notSelf = el.dataset.fieldId !== _dndSource.fieldId;
        // و هرگز روی نوادهٔ خودش: حلقه می‌سازد و کل زیردرخت را نامرئی می‌کند
        return notSelf && (isOption || isField) && !_isInsideDraggedSubtree(el);
    }
    if (_dndSource.kind === 'option') {
        // option → فقط روی field (type=option) دیگر، و نه داخل زیرمجموعه‌ی خودش
        return isField && fType === 'option'
            && el.dataset.fieldId !== _dndSource.ownerFieldId
            && !_isInsideDraggedSubtree(el);
    }
    return false;
}

/**
 * آیا گره‌ی مقصد داخل زیردرختِ همان چیزی است که داریم درگ می‌کنیم؟
 *
 * درخت در DOM تودرتو رندر می‌شود، پس کافی است از مقصد رو به بالا برویم و
 * ببینیم به li خودِ منبع می‌رسیم یا نه — بدون هیچ رفت‌وبرگشتی با سرور.
 */
function _isInsideDraggedSubtree(el) {
    if (!_dndSource) return false;

    const srcSel = _dndSource.kind === 'field'
        ? `.vtree-node[data-field-id="${_dndSource.fieldId}"]:not([data-option-id])`
        : `.vtree-node[data-option-id="${_dndSource.optionId}"]`;

    const srcNode = document.querySelector(srcSel);
    const srcLi   = srcNode?.closest('li');
    if (!srcLi) return false;

    return srcLi.contains(el) && el !== srcNode;
}

// ── Drop
async function vtreeDrop(e, el) {
    e.preventDefault();
    e.stopPropagation();
    el.classList.remove('vtree-drop-ok', 'vtree-drop-no', 'vtree-dragging');
    document.querySelectorAll('.vtree-dragging').forEach(n => n.classList.remove('vtree-dragging'));

    if (!_dndSource || !_dropOk(el)) { _dndSource = null; return; }

    const catId = _catId();
    let ok = false;

    // ── Palette drop — فوری ایجاد کن، بعد popover ویرایش باز کن
    if (_dndSource.kind === 'palette') {
        const palType = _dndSource.type;
        _dndSource = null;

        if (el.dataset.optionId) {
            // روی گزینه → فیلد زیرمجموعه شرطی بساز
            await _paletteCreateField(el.dataset.optionId, palType);
        } else if (el.dataset.fieldId && el.dataset.type === 'option') {
            // روی فیلد گزینه‌ای → گزینه جدید بساز
            await _paletteCreateOption(el.dataset.fieldId);
        } else if (el.dataset.fieldId) {
            // روی هر فیلد دیگری → زیرفیلد همیشگی.
            // اگر مقصد از قبل زیرفیلد همیشگی دارد، «زیرش بگذار» مبهم است:
            // شاخه‌ی موازی یا درج در زنجیره؟ قبلاً همیشه موازی می‌ساخت و
            // کاربر فکر می‌کرد درگ اصلاً کار نکرده.
            if (_alwaysChildNodes(el).length === 0) {
                await _paletteCreateAlwaysChildField(el.dataset.fieldId, palType);
            } else {
                const mode = await _askInsertMode(el.dataset.label || 'این فیلد');
                if (mode === 'chain')       await _paletteInsertInChain(el.dataset.fieldId, palType);
                else if (mode === 'branch') await _paletteCreateAlwaysChildField(el.dataset.fieldId, palType);
            }
        }
        return;
    }

    // ── Field reparent (روی option یا روی field برای always-child)
    if (_dndSource.kind === 'field' && (el.dataset.optionId || el.dataset.fieldId)) {
        const body = el.dataset.optionId
            ? { parent_option_id: el.dataset.optionId, parent_field_id: null }
            : { parent_option_id: null, parent_field_id: el.dataset.fieldId };
        const res = await fetch(`/admin/categories/${catId}/fields/${_dndSource.fieldId}/reparent`, {
            method: 'PATCH',
            body: JSON.stringify(body),
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        });
        const d = await res.json();
        ok = d.success;
        if (ok) {
            _pushUndo({
                type: 'reparent_field',
                fieldId: _dndSource.fieldId,
                oldParentOptionId: _dndSource.oldParentOptionId,
                oldParentFieldId:  _dndSource.oldParentFieldId,
                label: 'جابجایی فیلد',
            });
            treeToast('✅ فیلد جابجا شد');
        } else treeToast('❌ ' + (d.message || 'خطا'), false);
    }

    // ── Option reparent
    if (_dndSource.kind === 'option' && el.dataset.fieldId) {
        const oldFieldId = _dndSource.ownerFieldId;
        const res = await fetch(`/admin/categories/${catId}/fields/${oldFieldId}/options/${_dndSource.optionId}/reparent`, {
            method: 'PATCH',
            body: JSON.stringify({ field_id: el.dataset.fieldId }),
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        });
        const d = await res.json();
        ok = d.success;
        if (ok) {
            _pushUndo({ type: 'reparent_option', optionId: _dndSource.optionId, oldFieldId, label: 'جابجایی گزینه' });
            treeToast('✅ گزینه جابجا شد');
        } else treeToast('❌ ' + (d.message || 'خطا'), false);
    }

    _dndSource = null;
    if (ok) await refreshTree();
}

// dragend پاک‌سازی
document.addEventListener('dragend', () => {
    document.querySelectorAll('.vtree-dragging,.vtree-drop-ok,.vtree-drop-no').forEach(n => {
        n.classList.remove('vtree-dragging', 'vtree-drop-ok', 'vtree-drop-no');
    });
    _dndSource = null;
});

// ─── Intercept tree form submissions ────────────────────────────────────────
document.addEventListener('submit', async function (e) {
    const form = e.target;
    const inTree     = treeContainer?.contains(form);
    const isRootForm = form.closest('.lg\\:col-span-2') && !treeContainer?.contains(form);
    if (!inTree && !isRootForm) return;

    // فرم مشخصات دسته (ستون چپ) رو رها کن
    if (form.querySelector('input[name="name"]') && !form.querySelector('input[name="label"]')) return;

    e.preventDefault();

    const btn = form.querySelector('[type="submit"]');
    if (btn) { btn.disabled = true; btn.style.opacity = '0.6'; }

    try {
        const res = await fetch(form.action, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
        });
        const data = await res.json();

        if (res.ok && data.success) {
            treeToast('✅ ' + (data.message || 'انجام شد'));
            if (isRootForm) form.reset();
            await refreshTree();
        } else {
            const errs = data.errors ? Object.values(data.errors).flat().join(' | ') : (data.message || 'خطا');
            treeToast('❌ ' + errs, false);
            if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
        }
    } catch {
        treeToast('❌ خطا در ارتباط با سرور', false);
        if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
    }
});

// اطمینان از اینکه popover مستقیم در body باشه (برای fixed positioning و view:cache)
(function () {
    const pop = document.getElementById('vtree-popover');
    if (pop && pop.parentNode !== document.body) {
        document.body.appendChild(pop);
    }
})();

// auto-fit zoom هنگام load اولیه
(function () {
    // کمی تأخیر تا tree کاملاً render شده باشد
    setTimeout(vtreeZoomFit, 150);
})();
</script>
@endpush
