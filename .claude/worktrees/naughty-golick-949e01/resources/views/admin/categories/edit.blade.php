@extends('layouts.app')

@section('content')
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-gray-800">مدیریت دسته‌بندی: {{ $category->name }}</h2>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">{{ session('success') }}</div>
    @endif

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- ویرایش مشخصات اصلی دسته -->
        <div class="lg:col-span-1">
            <div class="bg-white border rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4 border-b pb-2">مشخصات دسته‌بندی</h3>
                <form action="{{ route('admin.categories.update', $category) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">عنوان</label>
                        <input type="text" name="name" value="{{ $category->name }}" class="py-2 px-3 block w-full border border-gray-200 rounded-lg text-sm" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">ترتیب</label>
                        <input type="number" name="sort_order" value="{{ $category->sort_order }}" class="py-2 px-3 block w-full border border-gray-200 rounded-lg text-sm">
                    </div>
                    <div class="mb-4 flex items-center">
                        <input type="checkbox" name="is_active" id="is_active" value="1" {{ $category->is_active ? 'checked' : '' }} class="shrink-0 border-gray-200 rounded text-blue-600 focus:ring-blue-500">
                        <label for="is_active" class="text-sm ms-3">وضعیت فعال باشد</label>
                    </div>
                    <button type="submit" class="w-full py-2 px-3 bg-blue-600 text-white rounded-lg text-sm font-semibold">بروزرسانی مشخصات</button>
                </form>
            </div>
        </div>

        <!-- مدیریت فیلدهای داینامیک -->
        <div class="lg:col-span-2">
            <!-- فرم افزودن فیلد جدید -->
            <div class="bg-gray-50 border rounded-xl shadow-sm p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4 text-gray-800">افزودن فیلد جدید</h3>
                <form action="{{ route('admin.categories.fields.store', $category) }}" method="POST" class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-end">
                    @csrf
                    <div class="sm:col-span-2">
                        <label class="block text-sm mb-1 text-gray-600">عنوان سوال <span class="text-red-500">*</span></label>
                        <input type="text" name="label" class="py-2 px-3 block w-full border border-gray-300 rounded-lg text-sm" placeholder="مثلاً: عکس محصول" required>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm mb-1 text-gray-600">توضیح راهنما (اختیاری)</label>
                        <input type="text" name="description" class="py-2 px-3 block w-full border border-gray-300 rounded-lg text-sm" placeholder="مثلاً: یک عکس واضح از محصول ارسال کنید">
                    </div>
                    <div>
                        <label class="block text-sm mb-1 text-gray-600">نوع فیلد <span class="text-red-500">*</span></label>
                        <select name="type" class="py-2 px-3 block w-full border border-gray-300 rounded-lg text-sm">
                            <option value="text">متن ساده</option>
                            <option value="photo">عکس</option>
                            <option value="document">فایل</option>
                            <option value="link">لینک</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm mb-1 text-gray-600">ترتیب نمایش</label>
                        <input type="number" name="sort_order" value="{{ $category->fields->count() }}" class="py-2 px-3 block w-full border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div class="sm:col-span-2 flex flex-wrap gap-x-6 gap-y-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_required" value="1" checked class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="text-sm text-gray-700">اجباری</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_multiple" value="1" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                            <span class="text-sm text-gray-700">ارسال چندتایی</span>
                            <span class="text-xs text-gray-400">(کاربر می‌تواند چند آیتم ارسال کند)</span>
                        </label>
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="w-full py-2 px-4 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700">افزودن فیلد</button>
                    </div>
                </form>
            </div>

            <!-- لیست فیلدهای موجود -->
            <div class="bg-white border rounded-xl shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 border-b">
                    <h3 class="text-sm font-semibold text-gray-700">فیلدهای تعریف‌شده — {{ $category->fields->count() }} فیلد</h3>
                </div>
                <div class="divide-y divide-gray-100">
                @forelse($category->fields as $field)
                    <details class="group">
                        <summary class="flex items-center justify-between px-4 py-3 cursor-pointer hover:bg-gray-50 list-none">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs text-gray-400 w-4 shrink-0">{{ $field->sort_order }}</span>
                                <span class="text-sm font-medium text-gray-800">{{ $field->label }}</span>
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium
                                    {{ $field->type === 'photo'    ? 'bg-blue-100 text-blue-700'   : '' }}
                                    {{ $field->type === 'document' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                    {{ $field->type === 'text'     ? 'bg-gray-100 text-gray-600'   : '' }}
                                    {{ $field->type === 'link'     ? 'bg-green-100 text-green-700' : '' }}
                                ">{{ $field->type_fa }}</span>
                                @if($field->is_multiple)
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 font-medium">چندتایی</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-xs text-blue-500 group-open:hidden">ویرایش ▾</span>
                                <form action="{{ route('admin.categories.fields.destroy', [$category, $field]) }}" method="POST" onsubmit="return confirm('این فیلد حذف شود؟');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-500 hover:text-red-700 px-2 py-1 rounded hover:bg-red-50">حذف</button>
                                </form>
                            </div>
                        </summary>
                        <!-- فرم ویرایش -->
                        <div class="px-4 pb-4 pt-3 bg-blue-50 border-t border-blue-100">
                            <form action="{{ route('admin.categories.fields.update', [$category, $field]) }}" method="POST" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @csrf
                                @method('PUT')
                                <div class="sm:col-span-2">
                                    <label class="block text-xs mb-1 text-gray-500">عنوان سوال</label>
                                    <input type="text" name="label" value="{{ $field->label }}" class="py-2 px-3 block w-full border border-gray-300 rounded-lg text-sm" required>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs mb-1 text-gray-500">توضیح راهنما</label>
                                    <input type="text" name="description" value="{{ $field->description }}" class="py-2 px-3 block w-full border border-gray-300 rounded-lg text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs mb-1 text-gray-500">ترتیب</label>
                                    <input type="number" name="sort_order" value="{{ $field->sort_order }}" class="py-2 px-3 block w-full border border-gray-300 rounded-lg text-sm">
                                </div>
                                <div class="flex flex-col gap-2 justify-end pb-1">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="is_required" value="1" {{ $field->is_required ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600">
                                        <span class="text-xs text-gray-700">اجباری</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="is_multiple" value="1" {{ $field->is_multiple ? 'checked' : '' }} class="rounded border-gray-300 text-purple-600">
                                        <span class="text-xs text-gray-700">ارسال چندتایی</span>
                                    </label>
                                </div>
                                <div class="sm:col-span-2">
                                    <button type="submit" class="w-full py-1.5 px-3 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700">ذخیره تغییرات</button>
                                </div>
                            </form>
                        </div>
                    </details>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-gray-500">هیچ فیلدی تعریف نشده است.</div>
                @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
