<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'پنل نظارت پلتفرم')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- رنگ و ساختار عمداً با پنل سازمانی فرق دارد تا یک لحظه هم این دو با هم اشتباه نشوند --}}
<body class="bg-slate-100 font-sans min-h-screen">

<div class="bg-amber-400 text-amber-950 text-xs text-center py-1.5 font-medium">
    پنل نظارت پلتفرم — این‌جا داده‌ی سازمان‌ها فقط دیده می‌شود، ویرایش نمی‌شود.
</div>

<header class="bg-slate-900 text-slate-100">
    <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-6">
            <a href="{{ route('platform.dashboard') }}" class="font-bold">پلتفرم</a>
            <nav class="flex items-center gap-4 text-sm">
                @php $nav = [
                    'platform.dashboard'            => 'داشبورد',
                    'platform.tenants.index'        => 'سازمان‌ها',
                    'platform.discount-codes.index' => 'کدهای تخفیف',
                    'platform.settings.edit'        => 'تنظیمات',
                ]; @endphp
                @foreach($nav as $route => $label)
                    <a href="{{ route($route) }}"
                       class="px-2 py-1 rounded {{ request()->routeIs(str_replace(['.index', '.edit'], '.*', $route)) ? 'bg-slate-700 text-white' : 'text-slate-300 hover:text-white' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
        </div>
        <div class="flex items-center gap-4 text-sm">
            <span class="text-slate-300">{{ auth()->user()->name }}</span>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button class="text-red-300 hover:text-red-200">خروج</button>
            </form>
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-4 py-8">
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
