<!DOCTYPE html>
<html lang="fa" dir="rtl" class="relative min-h-full" data-theme="theme-default" data-brand="blue" data-font="sans">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>داشبورد گزارشات حوزه هنری</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="hs-overlay-body-open overflow-hidden bg-background-2">


<script>
    (function () {
        const html = document.querySelector('html');
        const isLightOrAuto = localStorage.getItem('hs_theme') === 'light' || (localStorage.getItem('hs_theme') === 'auto' && !window.matchMedia('(prefers-color-scheme: dark)').matches);
        const isDarkOrAuto = localStorage.getItem('hs_theme') === 'dark' || (localStorage.getItem('hs_theme') === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);

        if (isLightOrAuto && html.classList.contains('dark')) html.classList.remove('dark');
        else if (isDarkOrAuto && !html.classList.contains('light')) html.classList.remove('light');
        else if (isDarkOrAuto && !html.classList.contains('dark')) html.classList.add('dark');
        else if (isLightOrAuto && !html.classList.contains('light')) html.classList.add('light');
    })();
</script>
<!-- ========== HEADER ========== -->
<header class="fixed top-0 inset-x-0 flex flex-wrap md:justify-start md:flex-nowrap z-48 w-full bg-navbar-2 text-sm py-2.5">
    <nav class="px-4 sm:px-5.5 flex basis-full items-center w-full mx-auto">
        <div class="w-full flex items-center justify-between gap-x-1.5">

            <!-- سمت راست هدر (لوگو و برند) - در راست‌چین این بخش در سمت راست است -->
            <ul class="flex items-center gap-1.5">
                <li class="inline-flex items-center gap-1 relative pe-1.5">
                    <a class="shrink-0 inline-flex justify-center items-center size-12 rounded-md text-xl font-semibold focus:outline-hidden focus:opacity-80"
                       href="{{ route('admin.dashboard') }}">
                        <img src="/favicon.ico" alt="logo" class="shrink-0 size-12 text-white" width="52" height="52" >
                    </a>

                    <!-- دکمه باز/بستن منو در موبایل (منو از راست ظاهر می‌شود) -->
                    <button type="button"
                            class="p-1.5 size-7.5 inline-flex items-center gap-x-1 text-xs rounded-md border border-transparent text-foreground hover:bg-surface-hover disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-surface-focus"
                            aria-haspopup="dialog" aria-expanded="false" aria-controls="hs-pro-sidebar"
                            data-hs-overlay="#hs-pro-sidebar">
                        <svg class="shrink-0 size-3.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <rect width="18" height="18" x="3" y="3" rx="2"/>
                            <path d="M15 3v18"/>
                            <path d="m10 15-3-3 3-3"/>
                        </svg>
                        <span class="sr-only">Toggle Sidebar</span>
                    </button>
                </li>
                <li class="hidden md:block font-bold text-foreground pr-2 text-lg">
                    سامانه گزارش‌گیری حوزه هنری
                </li>
            </ul>

            <!-- سمت چپ هدر (پروفایل و خروج) - در راست‌چین این بخش در سمت چپ خواهد بود -->
            <ul class="flex flex-row items-center gap-x-3 ms-auto">
                <li>
                    <div class="hs-dropdown inline-flex [--strategy:absolute] [--auto-close:inside] [--placement:bottom-left] relative text-start">
                        <button id="hs-dnad" type="button"
                                class="inline-flex items-center gap-x-2 py-1.5 px-3 bg-white border border-gray-200 text-sm font-medium rounded-full shadow-sm hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none"
                                aria-haspopup="menu" aria-expanded="false">
                            {{ auth()->user()?->name ?? 'کاربر' }}
                            <svg class="hs-dropdown-open:rotate-180 size-4" xmlns="http://www.w3.org/2000/svg"
                                 width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m6 9 6 6 6-6"/>
                            </svg>
                        </button>

                        <div class="hs-dropdown-menu hs-dropdown-open:opacity-100 end-0 w-50 transition-[opacity,margin] duration opacity-0 hidden z-20 bg-dropdown border border-dropdown-line rounded-xl shadow-xl"
                             role="menu" aria-labelledby="hs-dnad">
                            <div class="px-4 py-2 border-b border-dropdown-divider">
                                <div class="flex flex-wrap justify-between items-center gap-2">
                                    <span class="flex-1 cursor-pointer text-sm text-foreground">قالب ظاهری</span>
                                    <div class="p-0.5 inline-flex cursor-pointer bg-surface rounded-full">
                                        <button type="button"
                                                class="size-7 flex justify-center items-center bg-layer shadow-sm text-layer-foreground rounded-full hs-auto-mode-active:bg-transparent hs-dark-mode-active:bg-transparent"
                                                data-hs-theme-click-value="default">
                                            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24"
                                                 height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="12" cy="12" r="4"/>
                                                <path d="M12 3v1"/>
                                                <path d="M12 20v1"/>
                                                <path d="M3 12h1"/>
                                                <path d="M20 12h1"/>
                                                <path d="m18.364 5.636-.707.707"/>
                                                <path d="m6.343 17.657-.707.707"/>
                                                <path d="m5.636 5.636.707.707"/>
                                                <path d="m17.657 17.657.707.707"/>
                                            </svg>
                                        </button>
                                        <button type="button"
                                                class="size-7 flex justify-center items-center text-layer-foreground rounded-full hs-dark-mode-active:bg-secondary-active hs-dark-mode-active:text-secondary-foreground hs-dark-mode-active:shadow-sm"
                                                data-hs-theme-click-value="dark">
                                            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24"
                                                 height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="p-1">
                                <form action="{{ route('logout') }}" method="POST">
                                    @csrf
                                    <button type="submit"
                                            class="w-full flex items-center gap-x-3 py-2 px-3 rounded-lg text-sm text-dropdown-item-foreground hover:bg-dropdown-item-hover text-red-500 focus:outline-hidden">
                                        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24"
                                             height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="m16 17 5-5-5-5"/>
                                            <path d="M21 12H9"/>
                                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                        </svg>
                                        خروج از سیستم
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </nav>
</header>
<!-- ========== END HEADER ========== -->

<!-- ========== MAIN CONTENT ========== -->
<main class="lg:hs-overlay-layout-open:ps-60 transition-all duration-300 lg:fixed lg:inset-0 pt-13.5 px-3 pb-3">
    <!-- Sidebar (منوی راست) -->
    <div id="hs-pro-sidebar"
         class="hs-overlay [--body-scroll:true] lg:[--overlay-backdrop:false] [--is-layout-affect:true] [--opened:lg] [--auto-close:lg]
                hs-overlay-open:translate-x-0 lg:hs-overlay-layout-open:translate-x-0
                translate-x-full transition-all duration-300 transform
                w-60 hidden fixed inset-y-0 z-60 bg-sidebar-2
                lg:block lg:translate-x-0 lg:start-0"
         role="dialog" tabindex="-1">
        <div class="lg:pt-13 relative flex flex-col h-full max-h-full">
            <!-- دکمه بستن در موبایل -->
            <div class="lg:hidden absolute end-3 top-3">
                <button type="button"
                        class="p-1.5 size-7.5 inline-flex relative top-[-7px] items-center gap-x-1 text-xs rounded-md text-muted-foreground-1"
                        aria-haspopup="dialog" aria-expanded="false" aria-controls="hs-pro-sidebar"
                        data-hs-overlay="#hs-pro-sidebar">
                    <svg class="shrink-0 size-3.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                         stroke-linejoin="round">
                        <path d="M18 6 6 18"/>
                        <path d="m6 6 12 12"/>
                    </svg>
                </button>
            </div>

            <nav class="p-3 size-full flex flex-col overflow-y-auto">
                <!-- منوی اصلی -->
                @php $u = auth()->user(); @endphp

                <!-- منوی اصلی -->
                <div class="pt-3 flex flex-col border-t border-sidebar-2-divider first:border-t-0 first:pt-0">
                    <span class="block pe-2.5 mb-2 font-medium text-xs uppercase text-muted-foreground-1">منوی اصلی</span>
                    <ul class="flex flex-col gap-y-1">
                        <li>
                            <a class="w-full flex items-center gap-x-2 py-2 px-2.5 text-sm text-sidebar-2-nav-foreground rounded-lg hover:bg-sidebar-2-nav-hover focus:outline-hidden {{ request()->routeIs('admin.dashboard') ? 'bg-sidebar-2-nav-active font-bold' : '' }}"
                               href="{{ route('admin.dashboard') }}">داشبورد</a>
                        </li>
                        @if($u->hasPermission('settings'))
                        <li>
                            <a class="w-full flex items-center gap-x-2 py-2 px-2.5 text-sm text-sidebar-2-nav-foreground rounded-lg hover:bg-sidebar-2-nav-hover focus:outline-hidden {{ request()->routeIs('admin.settings.*') ? 'bg-sidebar-2-nav-active font-bold' : '' }}"
                               href="{{ route('admin.settings.index') }}">تنظیمات ربات</a>
                        </li>
                        @endif
                    </ul>
                </div>

                <!-- مدیریت اطلاعات -->
                @if($u->hasPermission('departments') || $u->hasPermission('representatives') || $u->hasPermission('categories'))
                <div class="pt-3 mt-3 flex flex-col border-t border-sidebar-2-divider">
                    <span class="block pe-2.5 mb-2 font-medium text-xs uppercase text-muted-foreground-1">مدیریت اطلاعات</span>
                    <ul class="flex flex-col gap-y-1">
                        @if($u->hasPermission('departments'))
                        <li>
                            <a class="w-full flex items-center gap-x-2 py-2 px-2.5 text-sm text-sidebar-2-nav-foreground rounded-lg hover:bg-sidebar-2-nav-hover focus:outline-hidden {{ request()->routeIs('admin.departments.*') ? 'bg-sidebar-2-nav-active font-bold' : '' }}"
                               href="{{ route('admin.departments.index') }}">دپارتمان‌ها</a>
                        </li>
                        @endif
                        @if($u->hasPermission('representatives'))
                        <li>
                            <a class="w-full flex items-center gap-x-2 py-2 px-2.5 text-sm text-sidebar-2-nav-foreground rounded-lg hover:bg-sidebar-2-nav-hover focus:outline-hidden {{ request()->routeIs('admin.representatives.*') ? 'bg-sidebar-2-nav-active font-bold' : '' }}"
                               href="{{ route('admin.representatives.index') }}">استان‌ها و نمایندگان</a>
                        </li>
                        @endif
                        @if($u->hasPermission('categories'))
                        <li>
                            <a class="w-full flex items-center gap-x-2 py-2 px-2.5 text-sm text-sidebar-2-nav-foreground rounded-lg hover:bg-sidebar-2-nav-hover focus:outline-hidden {{ request()->routeIs('admin.categories.*') ? 'bg-sidebar-2-nav-active font-bold' : '' }}"
                               href="{{ route('admin.categories.index') }}">فرم‌ساز و دسته‌بندی</a>
                        </li>
                        @endif
                    </ul>
                </div>
                @endif

                <!-- گزارش‌گیری -->
                @if($u->hasPermission('reports'))
                <div class="pt-3 mt-3 flex flex-col border-t border-sidebar-2-divider">
                    <span class="block pe-2.5 mb-2 font-medium text-xs uppercase text-muted-foreground-1">گزارش‌گیری</span>
                    <ul class="flex flex-col gap-y-1">
                        <li>
                            <a class="w-full flex items-center gap-x-2 py-2 px-2.5 text-sm text-sidebar-2-nav-foreground rounded-lg hover:bg-sidebar-2-nav-hover focus:outline-hidden {{ request()->routeIs('admin.reports.*') ? 'bg-sidebar-2-nav-active font-bold' : '' }}"
                               href="{{ route('admin.reports.index') }}">آرشیو گزارش‌ها</a>
                        </li>
                    </ul>
                </div>
                @endif

                <!-- مدیریت کاربران -->
                @if($u->hasPermission('users'))
                <div class="pt-3 mt-3 flex flex-col border-t border-sidebar-2-divider">
                    <span class="block pe-2.5 mb-2 font-medium text-xs uppercase text-muted-foreground-1">مدیریت دسترسی</span>
                    <ul class="flex flex-col gap-y-1">
                        <li>
                            <a class="w-full flex items-center gap-x-2 py-2 px-2.5 text-sm text-sidebar-2-nav-foreground rounded-lg hover:bg-sidebar-2-nav-hover focus:outline-hidden {{ request()->routeIs('admin.roles.*') ? 'bg-sidebar-2-nav-active font-bold' : '' }}"
                               href="{{ route('admin.roles.index') }}">نقش‌ها و دسترسی‌ها</a>
                        </li>
                        <li>
                            <a class="w-full flex items-center gap-x-2 py-2 px-2.5 text-sm text-sidebar-2-nav-foreground rounded-lg hover:bg-sidebar-2-nav-hover focus:outline-hidden {{ request()->routeIs('admin.users.*') ? 'bg-sidebar-2-nav-active font-bold' : '' }}"
                               href="{{ route('admin.users.index') }}">کاربران</a>
                        </li>
                    </ul>
                </div>
                @endif
            </nav>
        </div>
    </div>
    <!-- End Sidebar -->

    <!-- Content Box -->
    <div class="h-[calc(100dvh-62px)] lg:h-full overflow-hidden flex flex-col bg-layer border border-layer-line shadow-xs rounded-lg">
        <div class="flex-1 overflow-y-auto p-4 md:p-6 [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-thumb]:rounded-none [&::-webkit-scrollbar-track]:bg-scrollbar-track [&::-webkit-scrollbar-thumb]:bg-scrollbar-thumb lg:z-[500] lg:bg-white hs-dark-mode-active:bg-gray-500">
            @if(session('success'))
                <div class="bg-teal-100 border border-teal-200 text-teal-800 text-sm p-4 rounded-lg mb-6 shadow-sm"
                     role="alert">
                    <span class="font-bold">موفق!</span> {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="bg-red-100 border border-red-200 text-red-800 text-sm p-4 rounded-lg mb-6 shadow-sm"
                     role="alert">
                    <span class="font-bold">خطا!</span> {{ session('error') }}
                </div>
            @endif
            @if($errors->any())
                <div class="bg-red-100 border border-red-200 text-red-800 text-sm p-4 rounded-lg mb-6 shadow-sm"
                     role="alert">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </div>
    </div>
</main>

@stack('scripts')

<!-- Theme & Preline Initialization -->
<script>
    (function () {
        // Theme initialization
        const themeSwitcher = document.querySelectorAll('[data-hs-theme-click-value]');
        themeSwitcher.forEach(switcher => {
            switcher.addEventListener('click', () => {
                const val = switcher.getAttribute('data-hs-theme-click-value');
                localStorage.setItem('hs_theme', val);
                if (val === 'dark') {
                    document.documentElement.classList.add('dark');
                    document.documentElement.classList.remove('light');
                } else {
                    document.documentElement.classList.add('light');
                    document.documentElement.classList.remove('dark');
                }
            });
        });

        // Reinitialize Preline components after DOM ready (critical for production build)
        function initPreline() {
            if (window.HSStaticMethods) {
                window.HSStaticMethods.autoInit();
            }
        }

        document.addEventListener('DOMContentLoaded', initPreline);

        // Optional: If you use Livewire, uncomment the following lines
        // if (window.Livewire) {
        //     window.addEventListener('livewire:navigated', initPreline);
        // }
    })();
</script>
</body>
</html>