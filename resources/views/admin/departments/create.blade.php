@extends('layouts.app')

@section('content')
    <div class="mb-8 flex items-center gap-4">
        <a href="{{ route('admin.departments.index') }}" class="text-gray-400 hover:text-gray-600 transition-colors">
            <svg class="size-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m15 18-6-6 6-6"/>
            </svg>
        </a>
        <div>
            <h2 class="text-2xl font-bold text-gray-800">افزودن دپارتمان جدید</h2>
            <p class="text-sm text-gray-500 mt-1">اطلاعات واحد را وارد کنید</p>
        </div>
    </div>

    <div class="bg-white border rounded-xl shadow-sm p-6 max-w-xl">
        <form action="{{ route('admin.departments.store') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium mb-2">عنوان دپارتمان <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}"
                       class="py-3 px-4 block w-full border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500"
                       placeholder="مثلاً: روابط عمومی" required>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium mb-2">
                    ترتیب نمایش
                    <span class="text-xs text-gray-400 font-normal">(به صورت خودکار تعیین می‌شود)</span>
                </label>
                <input type="number" name="sort_order" value="{{ old('sort_order', $nextOrder) }}"
                       class="py-3 px-4 block w-full border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500"
                       min="0">
                <p class="text-xs text-gray-400 mt-1">ترتیب پیشنهادی: {{ $nextOrder }} — در صورت نیاز تغییر دهید.</p>
            </div>
            <div class="mb-6 flex items-center gap-3">
                <input type="checkbox" name="is_active" id="is_active" value="1" checked
                       class="shrink-0 border-gray-200 rounded text-blue-600 focus:ring-blue-500">
                <label for="is_active" class="text-sm">دپارتمان فعال باشد</label>
            </div>
            <div class="flex gap-3">
                <button type="submit"
                        class="py-2.5 px-5 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-lg bg-blue-600 text-white hover:bg-blue-700">
                    ذخیره
                </button>
                <a href="{{ route('admin.departments.index') }}"
                   class="py-2.5 px-5 inline-flex justify-center items-center text-sm font-medium rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50">
                    انصراف
                </a>
            </div>
        </form>
    </div>
@endsection
