<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ورود به مدیریت</title>
    @vite('resources/css/app.css')
    @vite('resources/js/app.js')
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Vazirmatn', 'sans-serif'] } } } }
    </script>
</head>
<body class="bg-gray-100 flex h-screen items-center py-16 font-sans">
<main class="w-full max-w-md mx-auto p-6">
    <div class="mt-7 bg-white border border-gray-200 rounded-xl shadow-sm">
        <div class="p-4 sm:p-7">
            <div class="text-center mb-8">
                <h1 class="block text-2xl font-bold text-gray-800">ورود به سیستم</h1>
                <p class="mt-2 text-sm text-gray-600">سامانه گزارش‌گیری حوزه هنری</p>
            </div>

            @if($errors->any())
                <div class="bg-red-100 border border-red-200 text-red-800 text-sm p-4 rounded-lg mb-4">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('login.post') }}" method="POST">
                @csrf
                <div class="grid gap-y-4">
                    <div>
                        <label for="email" class="block text-sm mb-2 text-gray-700">ایمیل</label>
                        <div class="relative">
                            <input type="email" id="email" name="email" class="py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 bg-gray-50 border" required aria-describedby="email-error" dir="ltr" value="{{ old('email') }}">
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <label for="password" class="block text-sm text-gray-700">رمز عبور</label>
                        </div>
                        <div class="relative">
                            <input type="password" id="password" name="password" class="py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 bg-gray-50 border" required aria-describedby="password-error" dir="ltr">
                        </div>
                    </div>

                    <button type="submit" class="w-full py-3 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none mt-4">
                        ورود
                    </button>
                </div>
            </form>
        </div>
    </div>

</main>
</body>
</html>