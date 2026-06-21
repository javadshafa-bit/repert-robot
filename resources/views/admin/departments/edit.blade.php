@extends('layouts.app')

@section('content')
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-gray-800">مدیریت دپارتمان: {{ $department->name }}</h2>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- ویرایش مشخصات اصلی دسته -->
        <div class="lg:col-span-1">
            <div class="bg-white border rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4 border-b pb-2">مشخصات دپارتمان</h3>
                <form action="{{ route('admin.departments.update', $department) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">عنوان</label>
                        <input type="text" name="name" value="{{ $department->name }}" class="py-2 px-3 block w-full border border-gray-200 rounded-lg text-sm" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">ترتیب</label>
                        <input type="number" name="sort_order" value="{{ $department->sort_order }}" class="py-2 px-3 block w-full border border-gray-200 rounded-lg text-sm">
                    </div>
                    <div class="mb-4 flex items-center">
                        <input type="checkbox" name="is_active" id="is_active" value="1" {{ $department->is_active ? 'checked' : '' }} class="shrink-0 mt-0.5 border-gray-200 rounded text-blue-600 focus:ring-blue-500">
                        <label for="is_active" class="text-sm ms-3">وضعیت فعال باشد</label>
                    </div>
                    <button type="submit" class="w-full py-2 px-3 bg-blue-600 text-white rounded-lg text-sm font-semibold">بروزرسانی مشخصات</button>
                </form>
            </div>
        </div>

    </div>
@endsection
