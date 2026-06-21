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
        <div class="bg-white border rounded-xl shadow-sm overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700">
                    ساختار درخت فیلدها — {{ $category->fields->count() }} فیلد سطح اول
                </h3>
            </div>

            @if($category->fields->isEmpty())
                <div class="px-4 py-10 text-center text-sm text-gray-400">هنوز فیلدی تعریف نشده</div>
            @else
                <div class="p-4 space-y-3">
                    @foreach($category->fields as $field)
                        @include('admin.categories._field_node', [
                            'field'    => $field,
                            'category' => $category,
                            'depth'    => 0,
                        ])
                    @endforeach
                </div>
            @endif
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleEdit(id) {
    const el = document.getElementById(id);
    if (el) el.classList.toggle('hidden');
}
</script>
@endpush
