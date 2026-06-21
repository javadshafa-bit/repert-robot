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
                    <label class="block text-sm mb-1 text-gray-600">نوع فیلد</label>
                    <select name="type" class="py-2 px-3 block w-full border border-gray-300 rounded-lg text-sm">
                        <option value="text">متن</option>
                        <option value="option">گزینه (شاخه‌ای)</option>
                        <option value="photo">عکس</option>
                        <option value="link">لینک</option>
                    </select>
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
             class="bg-white border rounded-xl shadow-sm overflow-hidden"
             data-tree-url="{{ route('admin.categories.tree-fragment', $category) }}">
            @include('admin.categories._tree_fragment', ['category' => $category])
        </div>

    </div>
</div>
@endsection

@push('scripts')
<style>
/* ─── Visual Tree CSS ───────────────────────────────────────────── */
.vtree-wrap { padding: 1.5rem 1rem; overflow-x: auto; min-height: 80px; }

.vtree, .vtree ul {
    list-style: none; margin: 0; padding: 0;
    display: flex; justify-content: center;
}
.vtree ul {
    padding-top: 24px;
    position: relative;
}
/* خط عمودی از والد به سطر فرزندان */
.vtree ul::before {
    content: '';
    position: absolute; top: 0; left: 50%;
    height: 24px; border-left: 2px solid #d1d5db;
}
.vtree li {
    display: flex; flex-direction: column; align-items: center;
    padding: 0 6px; position: relative;
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
    text-align: center; max-width: 130px; min-width: 72px;
    box-shadow: 0 1px 4px rgba(0,0,0,.08);
    padding: 5px 10px; cursor: default;
}
.vtree-option-node {
    border-radius: 99px !important;
    padding: 3px 12px !important;
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

/* ─── View toggle buttons ─── */
.tree-view-btn {
    background: #fff; color: #6b7280;
    border: 1px solid #e5e7eb;
}
.tree-view-btn.active {
    background: #4f46e5; color: #fff;
    border-color: #4f46e5;
}
</style>

<script>
function toggleEdit(id) {
    const el = document.getElementById(id);
    if (el) el.classList.toggle('hidden');
}

// ─── Tree view toggle ────────────────────────────────────────────────────────
let currentTreeView = 'visual';

function setTreeView(mode) {
    currentTreeView = mode;
    const visual = document.getElementById('tree-visual-view');
    const edit   = document.getElementById('tree-edit-view');
    const btnV   = document.getElementById('btn-visual');
    const btnE   = document.getElementById('btn-edit');
    if (visual) visual.classList.toggle('hidden', mode !== 'visual');
    if (edit)   edit.classList.toggle('hidden',   mode !== 'edit');
    if (btnV)   btnV.classList.toggle('active', mode === 'visual');
    if (btnE)   btnE.classList.toggle('active', mode === 'edit');
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
        setTreeView(currentTreeView); // حفظ حالت فعلی
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
</script>
@endpush
