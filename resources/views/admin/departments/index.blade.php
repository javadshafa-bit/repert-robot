@extends('layouts.app')

@section('content')
    <div class="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">دپارتمان‌ها</h2>
            <p class="text-sm text-gray-600 mt-1">مدیریت واحدها و دسته‌بندی‌های گزارش‌گیری</p>
        </div>
        <a href="{{ route('admin.departments.create') }}"
           class="py-2 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700">
            <svg class="size-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14"/><path d="M12 5v14"/>
            </svg>
            افزودن دپارتمان جدید
        </a>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
            <tr>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">#</th>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">عنوان دپارتمان</th>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">ترتیب</th>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">تعداد گزارش</th>
                <th scope="col" class="px-6 py-3 text-end   text-xs font-medium text-gray-500 uppercase">عملیات</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
            @forelse($departments as $i => $department)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">{{ $i + 1 }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-800">
                        <a href="{{ route('admin.departments.show', $department) }}"
                           class="hover:text-blue-600 transition-colors">
                            {{ $department->name }}
                        </a>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $department->sort_order }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if($department->is_active)
                            <span class="inline-flex items-center gap-1 py-0.5 px-2 rounded-md text-xs font-medium bg-green-100 text-green-700">
                                <span class="size-1.5 rounded-full bg-green-500"></span> فعال
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 py-0.5 px-2 rounded-md text-xs font-medium bg-red-100 text-red-700">
                                <span class="size-1.5 rounded-full bg-red-500"></span> غیرفعال
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $department->reports_count }} گزارش
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-end text-sm">
                        <div class="inline-flex items-center gap-x-3">
                            <a href="{{ route('admin.departments.show', $department) }}"
                               class="text-blue-600 hover:text-blue-800 font-medium transition-colors">پروفایل</a>
                            <a href="{{ route('admin.departments.edit', $department) }}"
                               class="text-gray-600 hover:text-gray-800 font-medium transition-colors">ویرایش</a>
                            <form action="{{ route('admin.departments.destroy', $department) }}" method="POST"
                                  class="inline-block"
                                  onsubmit="return confirm('با حذف دپارتمان، تمام گزارش‌های مرتبط با آن نیز حذف می‌شوند. مطمئن هستید؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="text-red-500 hover:text-red-700 font-medium transition-colors">حذف</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-6 py-10 text-center text-gray-400">هیچ دپارتمانی یافت نشد.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
