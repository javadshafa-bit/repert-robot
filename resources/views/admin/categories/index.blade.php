@extends('layouts.app')

@section('content')
    <div class="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">فرم‌ساز و دسته‌بندی‌ها</h2>
            <p class="text-sm text-gray-600 mt-1">مدیریت انواع گزارش‌ها (مثل تولید، رویداد) و فیلدهای هر کدام.</p>
        </div>
        <a href="{{ route('admin.categories.create') }}" class="py-2 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700">
            افزودن دسته‌بندی جدید
        </a>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
            <tr>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">عنوان دسته‌بندی</th>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">تعداد فیلدها</th>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">ترتیب نمایش</th>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                <th scope="col" class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase">عملیات</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
            @forelse($categories as $category)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800">{{ $category->name }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">{{ $category->fields_count }} فیلد</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">{{ $category->sort_order }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <form action="{{ route('admin.categories.toggle-active', $category) }}" method="POST" class="inline-block">
                            @csrf
                            @method('PATCH')
                            <button type="submit"
                                class="inline-flex items-center gap-x-1 text-sm font-semibold rounded-lg border border-transparent {{ $category->is_active ? 'text-green-600 hover:text-green-800' : 'text-red-500 hover:text-red-700' }}">
                                @if($category->is_active)
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    فعال
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    غیرفعال
                                @endif
                            </button>
                        </form>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-end text-sm font-medium">
                        <a href="{{ route('admin.categories.edit', $category) }}" class="inline-flex items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent text-blue-600 hover:text-blue-800">مدیریت فیلدها / ویرایش</a>
                        <span class="text-gray-300 mx-1">|</span>
                        <form action="{{ route('admin.categories.destroy', $category) }}" method="POST" class="inline-block" onsubmit="return confirm('با حذف دسته‌بندی، تمام گزارش‌های مرتبط با آن نیز حذف می‌شوند. مطمئن هستید؟');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent text-red-600 hover:text-red-800">حذف</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">هیچ دسته‌بندی یافت نشد.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection