@extends('layouts.app')

@section('content')
    <div class="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">فرم‌ساز و دپارتمان‌ها</h2>
            <p class="text-sm text-gray-600 mt-1">مدیریت انواع گزارش‌ها (مثل روابط عمومی، واحد اجرایی) و فیلدهای هر کدام.</p>
        </div>
        <a href="{{ route('admin.departments.create') }}" class="py-2 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700">
            افزودن دپارتمان جدید
        </a>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
            <tr>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">عنوان دپارتمان</th>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">ترتیب نمایش</th>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                <th scope="col" class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase">عملیات</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
            @forelse($departments as $department)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800">{{ $department->name }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">{{ $department->sort_order }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if($department->is_active)
                            <span class="text-green-600">فعال</span>
                        @else
                            <span class="text-red-600">غیرفعال</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-end text-sm font-medium">
                        <span class="text-gray-300 mx-1">|</span>
                        <form action="{{ route('admin.departments.destroy', $department) }}" method="POST" class="inline-block" onsubmit="return confirm('با حذف دپارتمان، تمام گزارش‌های مرتبط با آن نیز حذف می‌شوند. مطمئن هستید؟');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent text-red-600 hover:text-red-800">حذف</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">هیچ دپارتمان یافت نشد.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection