@extends('layouts.app')

@section('content')
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-gray-800">مدیریت دسته‌بندی: {{ $category->name }}</h2>
    </div>

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
                        <input type="checkbox" name="is_active" id="is_active" value="1" {{ $category->is_active ? 'checked' : '' }} class="shrink-0 mt-0.5 border-gray-200 rounded text-blue-600 focus:ring-blue-500">
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
                <h3 class="text-lg font-semibold mb-4 text-gray-800">افزودن فیلد جدید به این دسته</h3>
                <form action="{{ route('admin.categories.fields.store', $category) }}" method="POST" class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-end">
                    @csrf
                    <div class="sm:col-span-2">
                        <label class="block text-sm mb-1 text-gray-600">عنوان سوال (مثلاً: نام قالب)</label>
                        <input type="text" name="label" class="py-2 px-3 block w-full border border-gray-300 rounded-lg text-sm" required>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm mb-1 text-gray-600">توضیح راهنما برای کاربر (اختیاری)</label>
                        <input type="text" name="description" class="py-2 px-3 block w-full border border-gray-300 rounded-lg text-sm" placeholder="مثلاً: نام پروژه را وارد کنید">
                    </div>
                    <div>
                        <label class="block text-sm mb-1 text-gray-600">نوع فیلد</label>
                        <select name="type" class="py-2 px-3 block w-full border border-gray-300 rounded-lg text-sm">
                            <option value="text">متن ساده</option>
                            <option value="photo">عکس</option>
                            <option value="document">فایل</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm mb-1 text-gray-600">ترتیب</label>
                        <input type="number" name="sort_order" value="0" class="py-2 px-3 block w-full border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="w-full py-2 px-4 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700">افزودن فیلد</button>
                    </div>
                </form>
            </div>

            <!-- لیست فیلدهای موجود -->
            <div class="bg-white border rounded-xl shadow-sm overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-start text-xs font-medium text-gray-500">عنوان فیلد / سوال</th>
                        <th class="px-4 py-3 text-start text-xs font-medium text-gray-500">نوع</th>
                        <th class="px-4 py-3 text-start text-xs font-medium text-gray-500">ترتیب</th>
                        <th class="px-4 py-3 text-end text-xs font-medium text-gray-500">عملیات</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                    @forelse($category->fields as $field)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-800">{{ $field->label }}</td>
                            <td class="px-4 py-3 text-sm text-gray-800">{{ $field->type_fa }}</td>
                            <td class="px-4 py-3 text-sm text-gray-800">{{ $field->sort_order }}</td>
                            <td class="px-4 py-3 text-end text-sm">
                                <form action="{{ route('admin.categories.fields.destroy', [$category, $field]) }}" method="POST" class="inline-block" onsubmit="return confirm('آیا از حذف این فیلد مطمئن هستید؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 font-medium">حذف</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">هیچ فیلدی تعریف نشده است.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
