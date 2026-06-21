@extends('layouts.app')

@section('content')
    <div class="mb-8 flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">تنظیمات ربات بله</h2>
            <p class="text-sm text-gray-600 mt-1">مدیریت توکن، اتصال و پیام‌های پیش‌فرض ربات</p>
        </div>
        <div>
            @if($settings['bot_connected'] == '1')
                <span class="inline-flex items-center gap-1.5 py-1.5 px-3 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    <span class="size-1.5 inline-block bg-green-800 rounded-full"></span>
                    ربات متصل است
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 py-1.5 px-3 rounded-full text-xs font-medium bg-red-100 text-red-800">
                    <span class="size-1.5 inline-block bg-red-800 rounded-full"></span>
                    ربات قطع است
                </span>
            @endif
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        <!-- بخش اتصال ربات -->
        <div class="bg-white border rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">اتصال به بله (Webhook)</h3>
            <form action="{{ route('admin.settings.connect') }}" method="POST">
                @csrf
                <p class="text-sm text-gray-600 mb-4">برای دریافت پیام‌ها از سمت بله، باید یک بار روی دکمه زیر کلیک کنید تا آدرس سیستم شما در بله ثبت شود.</p>
                <button type="submit" class="py-2 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none">
                    ارسال درخواست اتصال به بله
                </button>
            </form>
        </div>

        <!-- بخش فرم تنظیمات -->
        <div class="bg-white border rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">ویرایش اطلاعات و پیام‌ها</h3>
            <form action="{{ route('admin.settings.update') }}" method="POST">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label for="bot_token" class="block text-sm font-medium mb-2">توکن ربات (Bot Token)</label>
                        <input type="text" id="bot_token" name="bot_token" value="{{ $settings['bot_token'] }}" class="py-3 px-4 block w-full border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500" dir="ltr" placeholder="123456789:ABCdefGhI...">
                    </div>

                    <div>
                        <label for="welcome_message" class="block text-sm font-medium mb-2">پیام خوش‌آمدگویی (شروع ربات)</label>
                        <textarea id="welcome_message" name="welcome_message" rows="3" class="py-3 px-4 block w-full border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500" required>{{ $settings['welcome_message'] }}</textarea>
                    </div>

                    <div>
                        <label for="error_message" class="block text-sm font-medium mb-2">پیام خطای عدم دسترسی (افراد غیرمجاز)</label>
                        <textarea id="error_message" name="error_message" rows="3" class="py-3 px-4 block w-full border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500" required>{{ $settings['error_message'] }}</textarea>
                    </div>
                </div>

                <div class="mt-6">
                    <button type="submit" class="py-2 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-green-600 text-white hover:bg-green-700">
                        ذخیره تنظیمات
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection