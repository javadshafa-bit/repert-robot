@extends('layouts.app')

@section('content')
    <div class="mb-8 flex items-center gap-4">
        <a href="{{ route('admin.broadcasts.index') }}" class="text-gray-400 hover:text-gray-600 transition-colors">
            <svg class="size-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m15 18-6-6 6-6"/>
            </svg>
        </a>
        <div>
            <h2 class="text-2xl font-bold text-gray-800">پیام همگانی جدید</h2>
            <p class="text-sm text-gray-500 mt-1">ارسال فوری یا زمان‌بندی‌شده بر اساس تقویم شمسی</p>
        </div>
    </div>

    @if($errors->any())
        <div class="mb-6 max-w-2xl p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            <ul class="list-disc ps-5 space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.broadcasts.store') }}" method="POST" enctype="multipart/form-data" class="max-w-2xl space-y-6">
        @csrf

        {{-- محتوای پیام --}}
        <div class="bg-white border rounded-xl shadow-sm p-6">
            <h3 class="text-base font-bold text-gray-800 mb-4">۱ — محتوای پیام</h3>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-2">عنوان <span class="text-red-500">*</span>
                    <span class="text-xs text-gray-400 font-normal">(فقط برای مدیریت داخلی؛ برای نمایندگان ارسال نمی‌شود)</span>
                </label>
                <input type="text" name="title" value="{{ old('title') }}" required
                       class="py-3 px-4 block w-full border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500"
                       placeholder="مثلاً: یادآوری ثبت گزارش تیر">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-2">متن پیام <span class="text-red-500">*</span></label>
                <textarea name="body" rows="5" required maxlength="4000"
                          class="py-3 px-4 block w-full border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500"
                          placeholder="متن پیامی که برای نمایندگان ارسال می‌شود...">{{ old('body') }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium mb-2">عکس <span class="text-xs text-gray-400 font-normal">(اختیاری — حداکثر ۵ مگابایت؛ متن به‌عنوان کپشن عکس ارسال می‌شود)</span></label>
                <input type="file" name="photo" accept="image/*"
                       class="block w-full text-sm text-gray-500 file:me-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-gray-200 rounded-lg">
            </div>
        </div>

        {{-- گیرندگان --}}
        <div class="bg-white border rounded-xl shadow-sm p-6">
            <h3 class="text-base font-bold text-gray-800 mb-4">۲ — گیرندگان</h3>

            <div class="flex items-center gap-6 mb-4">
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="radio" name="recipient_mode" value="all" class="text-blue-600 focus:ring-blue-500"
                           {{ old('recipient_mode', 'all') === 'all' ? 'checked' : '' }}>
                    همه استان‌ها
                </label>
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="radio" name="recipient_mode" value="selected" class="text-blue-600 focus:ring-blue-500"
                           {{ old('recipient_mode') === 'selected' ? 'checked' : '' }}>
                    استان‌های خاص
                </label>
            </div>

            <div id="province-list" class="hidden grid grid-cols-2 sm:grid-cols-3 gap-2 p-4 bg-gray-50 rounded-lg max-h-56 overflow-y-auto">
                @foreach($provinces as $province)
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="province_ids[]" value="{{ $province->id }}"
                               class="border-gray-200 rounded text-blue-600 focus:ring-blue-500"
                               {{ in_array($province->id, old('province_ids', [])) ? 'checked' : '' }}>
                        {{ $province->name }}
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-gray-400 mt-2">پیام فقط به نمایندگانی ارسال می‌شود که به ربات متصل شده‌اند.</p>
        </div>

        {{-- زمان‌بندی --}}
        <div class="bg-white border rounded-xl shadow-sm p-6">
            <h3 class="text-base font-bold text-gray-800 mb-4">۳ — زمان ارسال</h3>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-5">
                @foreach(['instant' => 'ارسال فوری', 'once' => 'یک‌بار در تاریخ مشخص', 'weekly' => 'هفتگی', 'monthly_jalali' => 'ماهانه (شمسی)'] as $value => $label)
                    <label class="schedule-tab flex items-center justify-center gap-2 text-sm cursor-pointer border rounded-lg py-2.5 px-3 text-center transition-colors
                                  {{ old('schedule_type', 'instant') === $value ? 'border-blue-500 bg-blue-50 text-blue-700 font-semibold' : 'border-gray-200 text-gray-600 hover:bg-gray-50' }}">
                        <input type="radio" name="schedule_type" value="{{ $value }}" class="hidden"
                               {{ old('schedule_type', 'instant') === $value ? 'checked' : '' }}>
                        {{ $label }}
                    </label>
                @endforeach
            </div>

            @php
                $months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
                $weekdays = \App\Models\BroadcastMessage::WEEKDAYS;
            @endphp

            {{-- یک‌بار: تاریخ شمسی --}}
            <div id="panel-once" class="schedule-panel hidden">
                <div class="grid grid-cols-3 gap-3 mb-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">سال</label>
                        <select name="jalali_year" class="py-3 px-4 block w-full border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                            @for($y = $jNow->getYear(); $y <= $jNow->getYear() + 2; $y++)
                                <option value="{{ $y }}" {{ (int) old('jalali_year', $jNow->getYear()) === $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">ماه</label>
                        <select name="jalali_month" class="py-3 px-4 block w-full border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                            @foreach($months as $i => $m)
                                <option value="{{ $i + 1 }}" {{ (int) old('jalali_month', $jNow->getMonth()) === $i + 1 ? 'selected' : '' }}>{{ $m }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">روز</label>
                        <select name="jalali_date" class="py-3 px-4 block w-full border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                            @for($d = 1; $d <= 31; $d++)
                                <option value="{{ $d }}" {{ (int) old('jalali_date', $jNow->getDay()) === $d ? 'selected' : '' }}>{{ $d }}</option>
                            @endfor
                        </select>
                    </div>
                </div>
            </div>

            {{-- هفتگی: روز هفته --}}
            <div id="panel-weekly" class="schedule-panel hidden">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">روز هفته</label>
                    <select name="day_of_week" class="py-3 px-4 block w-full sm:w-1/2 border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                        @foreach($weekdays as $i => $day)
                            <option value="{{ $i }}" {{ (int) old('day_of_week', 0) === $i ? 'selected' : '' }}>{{ $day }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- ماهانه شمسی: روز ماه --}}
            <div id="panel-monthly_jalali" class="schedule-panel hidden">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">روز ماه شمسی</label>
                    <select name="jalali_day" class="py-3 px-4 block w-full sm:w-1/2 border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                        @for($d = 1; $d <= 31; $d++)
                            <option value="{{ $d }}" {{ (int) old('jalali_day', 1) === $d ? 'selected' : '' }}>{{ $d }}</option>
                        @endfor
                    </select>
                    <p class="text-xs text-gray-400 mt-1">اگر ماه کوتاه‌تر باشد (مثلاً اسفند)، در آخرین روز ماه ارسال می‌شود.</p>
                </div>
            </div>

            {{-- ساعت ارسال (مشترک برای همه جز فوری) --}}
            <div id="panel-time" class="schedule-panel hidden">
                <label class="block text-sm font-medium mb-2">ساعت ارسال <span class="text-xs text-gray-400 font-normal">(به وقت تهران)</span></label>
                <input type="time" name="send_time" value="{{ old('send_time', '09:00') }}"
                       class="py-3 px-4 block w-full sm:w-1/3 border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
            </div>

            <p id="instant-hint" class="text-sm text-gray-500 bg-gray-50 rounded-lg p-3 hidden">
                پیام بلافاصله پس از ثبت برای همه گیرندگان ارسال می‌شود.
            </p>
        </div>

        <div class="flex gap-3">
            <button type="submit"
                    class="py-2.5 px-5 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-lg bg-blue-600 text-white hover:bg-blue-700">
                <svg class="size-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                <span id="submit-label">ارسال پیام</span>
            </button>
            <a href="{{ route('admin.broadcasts.index') }}"
               class="py-2.5 px-5 inline-flex justify-center items-center text-sm font-medium rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50">
                انصراف
            </a>
        </div>
    </form>

    <script>
        (function () {
            // نمایش/مخفی‌سازی لیست استان‌ها
            const provinceList = document.getElementById('province-list');
            document.querySelectorAll('input[name="recipient_mode"]').forEach(r => {
                r.addEventListener('change', syncProvinces);
            });
            function syncProvinces() {
                const selected = document.querySelector('input[name="recipient_mode"]:checked').value === 'selected';
                provinceList.classList.toggle('hidden', !selected);
                if (!selected) provinceList.querySelectorAll('input[type=checkbox]').forEach(c => c.checked = false);
            }
            syncProvinces();

            // پنل‌های زمان‌بندی
            const tabs = document.querySelectorAll('.schedule-tab');
            function syncSchedule() {
                const type = document.querySelector('input[name="schedule_type"]:checked').value;
                document.querySelectorAll('.schedule-panel').forEach(p => p.classList.add('hidden'));
                document.getElementById('instant-hint').classList.toggle('hidden', type !== 'instant');
                if (type !== 'instant') {
                    document.getElementById('panel-time').classList.remove('hidden');
                    const panel = document.getElementById('panel-' + type);
                    if (panel) panel.classList.remove('hidden');
                }
                document.getElementById('submit-label').textContent = type === 'instant' ? 'ارسال پیام' : 'زمان‌بندی پیام';
                tabs.forEach(t => {
                    const active = t.querySelector('input').checked;
                    t.classList.toggle('border-blue-500', active);
                    t.classList.toggle('bg-blue-50', active);
                    t.classList.toggle('text-blue-700', active);
                    t.classList.toggle('font-semibold', active);
                    t.classList.toggle('border-gray-200', !active);
                    t.classList.toggle('text-gray-600', !active);
                });
            }
            tabs.forEach(t => t.querySelector('input').addEventListener('change', syncSchedule));
            syncSchedule();
        })();
    </script>
@endsection
