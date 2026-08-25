<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'اشتراک و پرداخت')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- صفحه‌ی مستقل: سازمانِ پرداخت‌نکرده منوی پنل را نمی‌بیند تا روی لینک‌های بی‌اثر کلیک نکند --}}
<body class="bg-gray-100 font-sans min-h-screen">

<header class="bg-white border-b border-gray-200">
    <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
        <span class="font-bold text-gray-800">{{ $tenant->name }}</span>
        <div class="flex items-center gap-4 text-sm">
            @if($tenant->hasActiveSubscription())
                <a href="{{ route('admin.dashboard') }}" class="text-blue-600 hover:underline">ورود به پنل</a>
            @endif
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button class="text-red-600 hover:underline">خروج</button>
            </form>
        </div>
    </div>
</header>

<main class="max-w-4xl mx-auto px-4 py-8">
    @if(session('success'))
        <div class="bg-green-100 border border-green-200 text-green-800 text-sm p-4 rounded-lg mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-100 border border-red-200 text-red-800 text-sm p-4 rounded-lg mb-4">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-100 border border-red-200 text-red-800 text-sm p-4 rounded-lg mb-4">
            <ul class="list-disc pr-5 space-y-1">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>

</body>
</html>
