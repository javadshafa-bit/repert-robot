@extends('layouts.app')

@section('content')
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-gray-800">افزودن دسته‌بندی جدید</h2>
    </div>

    <div class="bg-white border rounded-xl shadow-sm p-6 max-w-xl">
        <form action="{{ route('admin.categories.store') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium mb-2">عنوان دسته‌بندی (مثلاً: تولید)</label>
                <input type="text" name="name" class="py-3 px-4 block w-full border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500" required>
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium mb-2">ترتیب نمایش در ربات (عدد)</label>
                <input type="number" name="sort_order" value="0" class="py-3 px-4 block w-full border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <button type="submit" class="py-2 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-lg bg-blue-600 text-white hover:bg-blue-700">ذخیره و ادامه به بخش فیلدها</button>
        </form>
    </div>
@endsection