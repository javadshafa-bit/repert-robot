<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ثبت‌نام سازمان</title>
    @vite('resources/css/app.css')
    @vite('resources/js/app.js')
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Vazirmatn', 'sans-serif'] } } } }
    </script>
</head>
<body class="bg-gray-100 flex min-h-screen items-center py-16 font-sans">
<main class="w-full max-w-md mx-auto p-6">
    <div class="mt-7 bg-white border border-gray-200 rounded-xl shadow-sm">
        <div class="p-4 sm:p-7">
            <div class="text-center mb-8">
                <h1 class="block text-2xl font-bold text-gray-800">ثبت‌نام سازمان</h1>
                <p class="mt-2 text-sm text-gray-600">پس از تایید سوپرادمین، پنل و ربات اختصاصی شما فعال می‌شود.</p>
            </div>

            @if($errors->any())
                <div class="bg-red-100 border border-red-200 text-red-800 text-sm p-4 rounded-lg mb-4">
                    <ul class="list-disc pr-5 space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('register.post') }}" method="POST">
                @csrf
                <div class="grid gap-y-4">
                    <div>
                        <label for="organization" class="block text-sm mb-2 text-gray-700">نام سازمان</label>
                        <input type="text" id="organization" name="organization" value="{{ old('organization') }}" required
                               class="py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 bg-gray-50 border">
                    </div>

                    <div>
                        <label for="owner_name" class="block text-sm mb-2 text-gray-700">نام و نام خانوادگی مدیر</label>
                        <input type="text" id="owner_name" name="owner_name" value="{{ old('owner_name') }}" required
                               class="py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 bg-gray-50 border">
                    </div>

                    <div>
                        <label for="owner_phone" class="block text-sm mb-2 text-gray-700">شماره تماس</label>
                        <input type="text" id="owner_phone" name="owner_phone" value="{{ old('owner_phone') }}" dir="ltr"
                               class="py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 bg-gray-50 border">
                    </div>

                    <div>
                        <label for="email" class="block text-sm mb-2 text-gray-700">ایمیل</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required dir="ltr"
                               class="py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 bg-gray-50 border">
                    </div>

                    <div>
                        <label for="password" class="block text-sm mb-2 text-gray-700">رمز عبور (حداقل ۸ کاراکتر)</label>
                        <input type="password" id="password" name="password" required dir="ltr"
                               class="py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 bg-gray-50 border">
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm mb-2 text-gray-700">تکرار رمز عبور</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" required dir="ltr"
                               class="py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 bg-gray-50 border">
                    </div>

                    <button type="submit" class="w-full py-3 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 mt-4">
                        ثبت درخواست
                    </button>

                    <p class="text-center text-sm text-gray-600 mt-2">
                        حساب دارید؟
                        <a href="{{ route('login') }}" class="text-blue-600 hover:underline">ورود</a>
                    </p>
                </div>
            </form>
        </div>
    </div>
</main>
</body>
</html>
