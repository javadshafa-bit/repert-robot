@extends('layouts.app')

@section('content')
    <div class="mb-8 flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">تنظیمات ربات بله</h2>
            <p class="text-sm text-gray-600 mt-1">مدیریت توکن، اتصال، پیام‌های پیش‌فرض و ترتیب مراحل</p>
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

        <!-- اتصال ربات -->
        <div class="bg-white border rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">اتصال به بله (Webhook)</h3>
            <form action="{{ route('admin.settings.connect') }}" method="POST">
                @csrf
                <p class="text-sm text-gray-600 mb-4">برای دریافت پیام‌ها از سمت بله، باید یک بار روی دکمه زیر کلیک کنید تا آدرس سیستم شما در بله ثبت شود.</p>
                <button type="submit" class="py-2 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700">
                    ارسال درخواست اتصال به بله
                </button>
            </form>
        </div>

        <!-- فرم تنظیمات -->
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
                        <label for="welcome_message" class="block text-sm font-medium mb-2">پیام خوش‌آمدگویی</label>
                        <textarea id="welcome_message" name="welcome_message" rows="3" class="py-3 px-4 block w-full border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500" required>{{ $settings['welcome_message'] }}</textarea>
                    </div>
                    <div>
                        <label for="error_message" class="block text-sm font-medium mb-2">پیام خطای عدم دسترسی</label>
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

        <!-- ترتیب مراحل ربات -->
        <div class="lg:col-span-2 bg-white border rounded-xl shadow-sm p-6">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">ترتیب مراحل دریافت گزارش</h3>
                    <p class="text-sm text-gray-500 mt-0.5">تعیین کنید ربات مراحل را به چه ترتیبی از کاربر بخواهد. فیلدهای گزارش همیشه بعد از این مراحل می‌آیند.</p>
                </div>
                <span class="text-xs text-gray-400 flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    برای تغییر ترتیب بکشید
                </span>
            </div>

            @php
            $allStepKeys = ['month', 'department', 'category'];
            $stepMeta = [
                'month'      => ['icon' => '🗓', 'label' => 'انتخاب ماه گزارش',  'desc' => 'کاربر از بین ۳ ماه اخیر انتخاب می‌کند',  'color' => 'bg-blue-50 border-blue-200',    'toggleable' => true],
                'department' => ['icon' => '🏢', 'label' => 'انتخاب دپارتمان',   'desc' => 'کاربر دپارتمان مربوطه را انتخاب می‌کند', 'color' => 'bg-orange-50 border-orange-200', 'toggleable' => true],
                'category'   => ['icon' => '📂', 'label' => 'انتخاب دسته‌بندی',  'desc' => 'نوع گزارش (فرم‌ساز) مشخص می‌شود',        'color' => 'bg-purple-50 border-purple-200', 'toggleable' => false],
            ];
            $activeSteps  = $flowSteps;
            $disabledSteps = array_values(array_diff($allStepKeys, $flowSteps));
            $orderedSteps  = array_merge($activeSteps, $disabledSteps);
            @endphp

            {{-- wrapper خارجی: dir=ltr تا flex ترتیب درست داشته باشه --}}
            <div id="flow-outer" dir="ltr" class="flex flex-col sm:flex-row sm:gap-10 items-stretch">

                <div id="flow-steps-sortable"
                     dir="ltr"
                     data-save-url="{{ route('admin.settings.flow') }}"
                     data-csrf="{{ csrf_token() }}"
                     class="flex flex-col sm:flex-row sm:gap-10 flex-[3]">

                    @foreach($orderedSteps as $stepKey)
                    @php
                        $meta      = $stepMeta[$stepKey];
                        $isEnabled = in_array($stepKey, $activeSteps);
                        $activeIdx = $isEnabled ? (array_search($stepKey, array_values($activeSteps)) + 1) : null;
                    @endphp
                    <div dir="rtl"
                         class="flow-step-item flex-1 relative border-2 rounded-xl p-4 transition-all duration-200 cursor-default select-none
                                {{ $isEnabled ? $meta['color'] : 'bg-gray-50 border-gray-200 opacity-50' }}"
                         data-step="{{ $stepKey }}"
                         data-enabled="{{ $isEnabled ? '1' : '0' }}">

                        <span class="flow-step-number absolute -top-3 -right-2 size-6 flex items-center justify-center bg-white border-2 border-gray-300 rounded-full text-xs font-bold text-gray-600">
                            {{ $isEnabled ? $activeIdx : '–' }}
                        </span>

                        @if($meta['toggleable'])
                        <button type="button"
                                onclick="toggleStep(this)"
                                class="step-toggle absolute top-2 left-2 text-xs px-2 py-0.5 rounded-full font-medium transition-colors
                                       {{ $isEnabled ? 'bg-green-100 text-green-700 hover:bg-red-100 hover:text-red-600' : 'bg-gray-200 text-gray-400 hover:bg-green-100 hover:text-green-700' }}">
                            {{ $isEnabled ? 'فعال' : 'غیرفعال' }}
                        </button>
                        @else
                        <span class="absolute top-2 left-2 text-xs px-2 py-0.5 rounded-full font-medium bg-purple-100 text-purple-500">اجباری</span>
                        @endif

                        <div class="flex items-start gap-3" dir="rtl">
                            @if($isEnabled)
                            <span class="drag-handle mt-0.5 text-gray-400 hover:text-gray-600 cursor-grab active:cursor-grabbing transition-colors shrink-0" title="بکشید">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="currentColor" viewBox="0 0 16 16">
                                    <circle cx="6" cy="3.5" r="1.2"/><circle cx="10" cy="3.5" r="1.2"/>
                                    <circle cx="6" cy="8"   r="1.2"/><circle cx="10" cy="8"   r="1.2"/>
                                    <circle cx="6" cy="12.5" r="1.2"/><circle cx="10" cy="12.5" r="1.2"/>
                                </svg>
                            </span>
                            @else
                            <span class="mt-1 text-gray-300 shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                </svg>
                            </span>
                            @endif
                            <div>
                                <div class="text-2xl mb-1">{{ $meta['icon'] }}</div>
                                <div class="font-semibold text-sm {{ $isEnabled ? 'text-gray-800' : 'text-gray-400' }}">{{ $meta['label'] }}</div>
                                <div class="text-xs mt-0.5 {{ $isEnabled ? 'text-gray-500' : 'text-gray-400' }}">{{ $meta['desc'] }}</div>
                            </div>
                        </div>
                    </div>
                    @endforeach

                </div>

                <div dir="rtl" class="flex-1 relative border-2 border-dashed border-gray-200 rounded-xl p-4 bg-gray-50 opacity-60">
                    <span class="absolute top-2 left-2 text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-400">اجباری</span>
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 text-gray-300 shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </span>
                        <div>
                            <div class="text-2xl mb-1">📝</div>
                            <div class="font-semibold text-gray-500 text-sm">فیلدهای گزارش</div>
                            <div class="text-xs text-gray-400 mt-0.5">ثابت — وابسته به دسته‌بندی</div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>
@endsection

@push('scripts')
<style>
    .sortable-drag   { opacity: 0 !important; }
    .sortable-ghost  { opacity: .5; }
    .sortable-chosen { box-shadow: 0 8px 25px -5px rgba(0,0,0,.15) !important; transform: scale(1.02); }
    .drag-handle     { touch-action: none; }
    #sort-toast      { animation: slideInLeft .3s ease; }
    @keyframes slideInLeft { from { opacity:0; transform:translateX(-1rem); } to { opacity:1; transform:translateX(0); } }

    @media (min-width: 640px) {
        #flow-steps-sortable > * + *,
        #flow-outer > div + div {
            position: relative;
        }
        #flow-steps-sortable > * + *::before,
        #flow-outer > div + div::before {
            content: '';
            position: absolute;
            right: calc(100% + 1.25rem);
            top: 50%;
            transform: translateY(-50%);
            width: 0; height: 0;
            border-top: 6px solid transparent;
            border-bottom: 6px solid transparent;
            border-left: 8px solid #d1d5db;
            pointer-events: none;
            z-index: 10;
        }
    }
</style>
@endpush
