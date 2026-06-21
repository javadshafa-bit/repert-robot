@extends('layouts.app')

@section('content')
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-gray-800">افزودن نماینده جدید</h2>
        <p class="text-sm text-gray-600 mt-1">مشخصات نماینده را وارد کنید. او می‌تواند با این شماره در ربات احراز هویت کند.</p>
    </div>

    <div class="bg-white border rounded-xl shadow-sm p-6 max-w-2xl">
        <form action="{{ route('admin.representatives.store') }}" method="POST">
            @csrf
            <div class="grid sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="first_name" class="block text-sm font-medium mb-2">نام</label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" class="py-3 px-4 block w-full border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500" required>
                </div>
                <div>
                    <label for="last_name" class="block text-sm font-medium mb-2">نام خانوادگی</label>
                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" class="py-3 px-4 block w-full border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500" required>
                </div>
            </div>

            <div class="mb-4">
                <label for="phone_number" class="block text-sm font-medium mb-2">شماره تماس بله (الزامی برای ربات)</label>
                <input type="text" id="phone_number" name="phone_number" value="{{ old('phone_number') }}" class="py-3 px-4 block w-full border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500" dir="ltr" placeholder="09123456789" required>
                <p class="text-xs text-gray-500 mt-1">شماره باید دقیقا با شماره اکانت بله کاربر یکسان باشد.</p>
            </div>

            <div class="mb-6">
                <label for="province_id" class="block text-sm font-medium mb-2">استان</label>
                <select id="province_id" name="province_id" class="py-3 px-4 block w-full border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500" required>
                    <option value="">انتخاب کنید...</option>
                    @foreach($provinces as $province)
                        <option value="{{ $province->id }}" {{ old('province_id') == $province->id ? 'selected' : '' }}>{{ $province->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="py-2 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700">ذخیره اطلاعات</button>
                <a href="{{ route('admin.representatives.index') }}" class="py-2 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-sm hover:bg-gray-50">انصراف</a>
            </div>
        </form>
    </div>
@endsection