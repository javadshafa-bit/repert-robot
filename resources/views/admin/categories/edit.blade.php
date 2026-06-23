@extends('layouts.app')

@section('content')
<div class="mb-8">
    <h2 class="text-2xl font-bold text-gray-800">مدیریت دسته‌بندی: {{ $category->name }}</h2>
</div>

@if(session('success'))
    <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">{{ session('success') }}</div>
@endif

<div class="grid lg:grid-cols-3 gap-6">

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
            </div>
        </div>
    </div>

    {{-- ستون راست: درخت فیلدها --}}
    <div class="lg:col-span-2 space-y-4">

        {{-- فرم افزودن فیلد سطح اول --}}
        <div class="bg-gray-50 border rounded-xl shadow-sm p-5">
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

        {{-- درخت فیلدها --}}
        <div id="tree-container"
             class="bg-white border rounded-xl shadow-sm"
             data-tree-url="{{ route('admin.categories.tree-fragment', $category) }}"
             data-category-id="{{ $category->id }}">
            @include('admin.categories._tree_fragment', ['category' => $category])
        </div>

    </div>
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
            <button onclick="vtreeDeleteField()"
                    class="py-2 px-3 bg-red-50 text-red-600 text-xs font-semibold rounded-lg hover:bg-red-100">حذف</button>
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

{{-- ─── Palette Panel ─────────────────────────────────────────────────────── --}}
<div id="vtree-palette"
     style="position:fixed;bottom:1.5rem;right:50%;transform:translateX(50%);z-index:900;
            background:#fff;border:1.5px solid #e5e7eb;border-radius:1rem;
            box-shadow:0 8px 30px rgba(0,0,0,.12);padding:.6rem .8rem;
            display:flex;align-items:center;gap:.5rem;user-select:none;">
    <span style="font-size:.7rem;color:#9ca3af;white-space:nowrap">درگ کنید:</span>
    <div class="vtree-palette-chip" draggable="true"
         ondragstart="vtreePaletteDrag(event,'text')"
         style="background:#f9fafb;border:1.5px solid #9ca3af;color:#374151">📝 متن</div>
    <div class="vtree-palette-chip" draggable="true"
         ondragstart="vtreePaletteDrag(event,'option')"
         style="background:#f5f3ff;border:1.5px solid #a78bfa;color:#5b21b6">🔘 گزینه</div>
    <div class="vtree-palette-chip" draggable="true"
         ondragstart="vtreePaletteDrag(event,'photo')"
         style="background:#eff6ff;border:1.5px solid #60a5fa;color:#1d4ed8">📸 عکس</div>
    <div class="vtree-palette-chip" draggable="true"
         ondragstart="vtreePaletteDrag(event,'link')"
         style="background:#f0fdf4;border:1.5px solid #4ade80;color:#15803d">🔗 لینک</div>
    <div style="width:1px;height:1.5rem;background:#e5e7eb;margin:0 .25rem"></div>
    {{-- copy section --}}
    <div id="palette-copy-section" style="display:none;align-items:center;gap:.4rem">
        <span id="palette-sel-count" style="font-size:.7rem;color:#6366f1;font-weight:600"></span>
        <button onclick="vtreeCopySelected()"
                style="font-size:.7rem;padding:.25rem .6rem;background:#6366f1;color:#fff;border:none;border-radius:.5rem;cursor:pointer">
            📋 کپی
        </button>
        <button onclick="vtreeClearSelection()"
                style="font-size:.7rem;padding:.25rem .5rem;background:#f3f4f6;color:#6b7280;border:1px solid #e5e7eb;border-radius:.5rem;cursor:pointer">
            ✕
        </button>
    </div>
    {{-- paste mode --}}
    <div id="palette-paste-section" style="display:none;align-items:center;gap:.4rem">
        <span style="font-size:.7rem;color:#f59e0b;font-weight:600">روی یک فیلد گزینه‌ای کلیک کنید تا paste شود</span>
        <button onclick="vtreeCancelPaste()"
                style="font-size:.7rem;padding:.25rem .5rem;background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;border-radius:.5rem;cursor:pointer">
            انصراف
        </button>
    </div>
</div>

@push('scripts')
<style>
/* ─── Visual Tree CSS ───────────────────────────────────────────── */
.vtree-wrap { padding: 2rem 1.5rem; overflow-x: auto; min-height: 100px; }

.vtree, .vtree ul {
    list-style: none; margin: 0; padding: 0;
    display: flex; justify-content: center;
}
.vtree ul {
    padding-top: 32px;
    position: relative;
}
/* خط عمودی از والد به سطر فرزندان */
.vtree ul::before {
    content: '';
    position: absolute; top: 0; left: 50%;
    height: 32px; border-left: 2px solid #d1d5db;
}
.vtree li {
    display: flex; flex-direction: column; align-items: center;
    padding: 0 14px; position: relative;
}
/* خطوط افقی بین برادرها */
.vtree li::before, .vtree li::after {
    content: ''; position: absolute; top: 0;
    border-top: 2px solid #d1d5db; width: 50%;
}
.vtree li::before { right: 50%; }
.vtree li::after  { left: 50%; }
.vtree li:first-child::before,
.vtree li:last-child::after { display: none; }
/* اگه فرزند یکیه خطوط افقی لازم نیست */
.vtree li:only-child::before,
.vtree li:only-child::after { display: none; }

/* ─── Nodes ─── */
.vtree-node {
    position: relative; z-index: 1;
    border: 1.5px solid; border-radius: 10px;
    text-align: center; max-width: 150px; min-width: 80px;
    box-shadow: 0 2px 6px rgba(0,0,0,.09);
    padding: 7px 14px; cursor: default;
    transition: box-shadow .15s, transform .15s;
}
.vtree-node:hover { box-shadow: 0 4px 12px rgba(99,102,241,.25); transform: translateY(-1px); }
.vtree-option-node {
    border-radius: 99px !important;
    padding: 4px 16px !important;
    background: #fff7ed; border-color: #fb923c; color: #9a3412;
}
.vtree-badge  { display: block; font-size: 9px; opacity: .6; margin-bottom: 1px; }
.vtree-label  { display: block; font-size: 11px; font-weight: 600;
                overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* نوع فیلد → رنگ */
.vtree-type-option { background:#f5f3ff; border-color:#a78bfa; color:#5b21b6; }
.vtree-type-text   { background:#f9fafb; border-color:#9ca3af; color:#374151; }
.vtree-type-photo  { background:#eff6ff; border-color:#60a5fa; color:#1d4ed8; }
.vtree-type-link   { background:#f0fdf4; border-color:#4ade80; color:#15803d; }

/* ─── Vtree hover highlight ─── */
.vtree-node:hover { filter: brightness(0.95); outline: 2px solid #6366f1; outline-offset: 1px; }

/* ─── Drag & Drop states ─── */
.vtree-node.vtree-dragging  { opacity: .4; }
.vtree-node.vtree-drop-ok   { outline: 2.5px dashed #22c55e !important; outline-offset: 3px; background-color: #f0fdf4 !important; transform: scale(1.05); }
.vtree-node.vtree-drop-no   { outline: 2px dashed #ef4444 !important; outline-offset: 2px; }
.vtree-node.vtree-selected  { outline: 2.5px solid #6366f1 !important; outline-offset: 2px; }
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
.vtree-always-ul {
    list-style: none; margin: 0; padding: 0;
    display: flex; flex-direction: column; align-items: center;
    padding-top: 4px;
}
.vtree-always-ul li {
    display: flex; flex-direction: column; align-items: center;
    padding: 0;
}
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
    treeContainer.style.opacity = '0.5';
    try {
        const res  = await fetch(TREE_URL, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        treeContainer.innerHTML = data.html;
    } finally {
        treeContainer.style.opacity = '1';
    }
}

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
    document.getElementById('vp-f-required').checked = el.dataset.isRequired === '1';
    document.getElementById('vp-f-multiple').checked = el.dataset.isMultiple === '1';
    document.getElementById('vp-f-multi-wrap').style.display = el.dataset.type === 'option' ? 'none' : '';
    // نمایش بخش افزودن گزینه فقط برای فیلدهای نوع گزینه
    document.getElementById('vp-f-add-opt-wrap').classList.toggle('hidden', el.dataset.type !== 'option');
    document.getElementById('vp-add-opt').classList.add('hidden');
    document.getElementById('vp-ao-label').value = '';
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
    fd.append('parent_field_id', _vpFieldId);
    fd.append('is_required',     '1');
    const res  = await fetch(`/admin/categories/${catId}/fields`, {
        method: 'POST', body: fd,
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
    });
    const data = await res.json();
    if (data.success) { vtreePopoverClose(); treeToast('✅ ' + data.message); await refreshTree(); }
    else treeToast('❌ ' + (data.message || 'خطا'), false);
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
        fd2.append('is_required',      '1');
        fd2.append('parent_option_id', data.option_id);
        await fetch(`/admin/categories/${catId}/fields`, {
            method: 'POST', body: fd2,
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        });
    }

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
    fd.append('parent_option_id', _vpOptId);
    if (document.getElementById('vp-af-required').checked) fd.append('is_required', '1');
    const res  = await fetch(`/admin/categories/${catId}/fields`, {
        method: 'POST', body: fd,
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
    });
    const data = await res.json();
    if (data.success) { vtreePopoverClose(); treeToast('✅ ' + data.message); await refreshTree(); }
    else treeToast('❌ ' + (data.message || 'خطا'), false);
}

async function vtreeSubmitField() {
    const catId = _catId();
    const fd = new FormData();
    fd.append('_method', 'PUT');
    fd.append('label',       document.getElementById('vp-f-label').value);
    fd.append('description', document.getElementById('vp-f-desc').value);
    fd.append('type',        document.getElementById('vp-f-type').value);
    if (document.getElementById('vp-f-required').checked) fd.append('is_required', '1');
    if (document.getElementById('vp-f-multiple').checked) fd.append('is_multiple', '1');
    const res  = await fetch(`/admin/categories/${catId}/fields/${_vpFieldId}`, {
        method: 'POST', body: fd,
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
    });
    const data = await res.json();
    if (data.success) { vtreePopoverClose(); treeToast('✅ ' + data.message); await refreshTree(); }
    else treeToast('❌ ' + (data.message || 'خطا'), false);
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

async function vtreeSubmitOption() {
    const catId = _catId();
    const fd = new FormData();
    fd.append('_method', 'PUT');
    fd.append('label', document.getElementById('vp-o-label').value);
    const res  = await fetch(`/admin/categories/${catId}/fields/${_vpOptFieldId}/options/${_vpOptId}`, {
        method: 'POST', body: fd,
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
    });
    const data = await res.json();
    if (data.success) { vtreePopoverClose(); treeToast('✅ ' + data.message); await refreshTree(); }
    else treeToast('❌ ' + (data.message || 'خطا'), false);
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

// ─── Drag & Drop + Multi-select ─────────────────────────────────────────────
let _dndSource  = null;  // { kind:'palette'|'field'|'option', type?, fieldId?, optionId?, ownerFieldId? }
let _selected   = [];    // [{ kind, id }]  — multi-select گزینه‌ها
let _pasteMode  = false;
let _clipboard  = [];    // option_ids to paste

// ── Click handler (click vs ctrl+click vs paste-click)
function vtreeNodeClick(e, el, kind) {
    e.stopPropagation();

    // paste mode: کلیک روی field از نوع option = paste
    if (_pasteMode) {
        if (kind === 'field' && el.dataset.type === 'option') vtreePasteHere(el);
        return;
    }

    // ctrl/cmd + click = toggle selection (only options)
    if ((e.ctrlKey || e.metaKey) && kind === 'option') {
        e.preventDefault();
        vtreeToggleSelect(el);
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

function vtreeClearSelection() {
    _selected = [];
    document.querySelectorAll('.vtree-selected').forEach(n => n.classList.remove('vtree-selected'));
    _updatePaletteCopySection();
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
    vtreeClearSelection();
    document.getElementById('palette-copy-section').style.display = 'none';
    document.getElementById('palette-paste-section').style.display = 'flex';
    // هایلایت targets
    document.querySelectorAll('.vtree-node[data-type="option"]').forEach(n => n.classList.add('vtree-paste-target'));
    treeToast('روی یک فیلد گزینه‌ای کلیک کنید تا paste شود', true);
}

function vtreeCancelPaste() {
    _pasteMode = false;
    _clipboard = [];
    document.getElementById('palette-paste-section').style.display = 'none';
    document.querySelectorAll('.vtree-paste-target').forEach(n => n.classList.remove('vtree-paste-target'));
}

async function vtreePasteHere(el) {
    const catId   = _catId();
    const fieldId = el.dataset.fieldId;
    document.querySelectorAll('.vtree-paste-target').forEach(n => n.classList.remove('vtree-paste-target'));
    document.getElementById('palette-paste-section').style.display = 'none';
    _pasteMode = false;

    const fd = new FormData();
    _clipboard.forEach(id => fd.append('option_ids[]', id));

    const res  = await fetch(`/admin/categories/${catId}/fields/${fieldId}/options/batch-copy`, {
        method: 'POST', body: fd,
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
    });
    const data = await res.json();
    if (data.success) { treeToast('✅ ' + data.message); await refreshTree(); }
    else treeToast('❌ ' + (data.message || 'خطا در paste'), false);
    _clipboard = [];
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
        _dndSource = { kind: 'field', fieldId: el.dataset.fieldId, type: el.dataset.type };
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
        return isOption || (isField && fType === 'option');
    }
    if (_dndSource.kind === 'field') {
        // field → فقط روی option دیگر (جابجایی)
        return isOption;
    }
    if (_dndSource.kind === 'option') {
        // option → فقط روی field (type=option) دیگر
        return isField && fType === 'option' && el.dataset.fieldId !== _dndSource.ownerFieldId;
    }
    return false;
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

    // ── Palette drop
    if (_dndSource.kind === 'palette') {
        if (el.dataset.optionId) {
            // روی option → باز کن popover «افزودن زیرفیلد» با type از قبل
            _vpOptId      = el.dataset.optionId;
            _vpOptFieldId = el.dataset.fieldId;
            document.getElementById('vp-af-type').value = _dndSource.type;
            document.getElementById('vp-af-label').value = '';
            document.getElementById('vp-add-field').classList.remove('hidden');
            document.getElementById('vp-option').classList.remove('hidden');
            document.getElementById('vp-field').classList.add('hidden');
            vtreePopoverShow(el);
            _dndSource = null;
            return;
        } else if (el.dataset.fieldId && el.dataset.type === 'option') {
            // روی field (option) → باز کن popover «افزودن گزینه»
            _vpFieldId = el.dataset.fieldId;
            document.getElementById('vp-ao-label').value = '';
            document.getElementById('vp-ao-child-type').value = '';
            document.getElementById('vp-ao-child-label-wrap').classList.add('hidden');
            document.getElementById('vp-add-opt').classList.remove('hidden');
            document.getElementById('vp-f-add-opt-wrap').classList.remove('hidden');
            document.getElementById('vp-field').classList.remove('hidden');
            document.getElementById('vp-option').classList.add('hidden');
            vtreePopoverShow(el);
            _dndSource = null;
            return;
        }
    }

    // ── Field reparent
    if (_dndSource.kind === 'field' && el.dataset.optionId) {
        const res = await fetch(`/admin/categories/${catId}/fields/${_dndSource.fieldId}/reparent`, {
            method: 'PATCH',
            body: JSON.stringify({ parent_option_id: el.dataset.optionId }),
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        });
        const d = await res.json();
        ok = d.success;
        if (ok) treeToast('✅ فیلد جابجا شد');
        else    treeToast('❌ ' + (d.message || 'خطا'), false);
    }

    // ── Option reparent
    if (_dndSource.kind === 'option' && el.dataset.fieldId) {
        const res = await fetch(`/admin/categories/${catId}/fields/${_dndSource.ownerFieldId}/options/${_dndSource.optionId}/reparent`, {
            method: 'PATCH',
            body: JSON.stringify({ field_id: el.dataset.fieldId }),
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        });
        const d = await res.json();
        ok = d.success;
        if (ok) treeToast('✅ گزینه جابجا شد');
        else    treeToast('❌ ' + (d.message || 'خطا'), false);
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
</script>
@endpush
