@extends('layouts.app')

@section('content')
    <div class="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">نمایندگان استان‌ها</h2>
            <p class="text-sm text-gray-600 mt-1">مدیریت افرادی که مجاز به استفاده از ربات هستند.</p>
        </div>
        <a href="{{ route('admin.representatives.create') }}" class="py-2 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700">
            <svg class="flex-shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            افزودن نماینده جدید
        </a>
    </div>

    <!-- فرم فیلتر -->
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm mb-6 p-4">
        <form action="{{ route('admin.representatives.index') }}" method="GET" class="grid sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium mb-2">جستجو (نام یا شماره)</label>
                <input type="text" name="search" value="{{ request('search') }}" class="py-2 px-3 block w-full border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500" placeholder="جستجو...">
            </div>
            <div>
                <label class="block text-sm font-medium mb-2">استان</label>
                <select name="province_id" class="py-2 px-3 block w-full border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">همه استان‌ها</option>
                    @foreach($provinces as $province)
                        <option value="{{ $province->id }}" {{ request('province_id') == $province->id ? 'selected' : '' }}>{{ $province->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="py-2 px-4 inline-flex w-full sm:w-auto justify-center items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-gray-800 text-white hover:bg-gray-900">
                    فیلتر
                </button>
                @if(request('search') || request('province_id'))
                    <a href="{{ route('admin.representatives.index') }}" class="py-2 px-4 ms-2 inline-flex justify-center items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-sm hover:bg-gray-50">
                        پاک کردن
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- جدول -->
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
            <tr>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">نام و نام خانوادگی</th>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">شماره تماس</th>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">استان</th>
                <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">وضعیت اتصال</th>
                <th scope="col" class="px-6 py-3 text-end text-xs font-medium text-gray-500 uppercase">عملیات</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
            @forelse($representatives as $rep)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800">{{ $rep->full_name }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800" dir="ltr">{{ $rep->phone_number }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">{{ $rep->province->name }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if($rep->is_connected)
                            <span class="inline-flex items-center gap-1.5 py-1 px-2 rounded-lg text-xs font-medium bg-green-100 text-green-800">متصل</span>
                        @else
                            <span class="inline-flex items-center gap-1.5 py-1 px-2 rounded-lg text-xs font-medium bg-red-100 text-red-800">در انتظار</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-end text-sm font-medium">
                        <a href="{{ route('admin.representatives.show', $rep) }}" class="inline-flex items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent text-gray-600 hover:text-gray-800">پروفایل</a>
                        <span class="text-gray-300 mx-1">|</span>
                        <a href="{{ route('admin.representatives.edit', $rep) }}" class="inline-flex items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent text-blue-600 hover:text-blue-800">ویرایش</a>
                        <span class="text-gray-300 mx-1">|</span>
                        <form action="{{ route('admin.representatives.destroy', $rep) }}" method="POST" class="inline-block" onsubmit="return confirm('آیا از حذف مطمئن هستید؟');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent text-red-600 hover:text-red-800">حذف</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">هیچ نماینده‌ای یافت نشد.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div class="px-6 py-4 border-t border-gray-200">
            {{ $representatives->links() }}
        </div>
    </div>
@endsection