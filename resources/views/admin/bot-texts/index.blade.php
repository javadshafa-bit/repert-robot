@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">متن‌های ربات</h2>
            <p class="text-sm text-gray-600 mt-1">
                هر پیام و متن دکمه‌ای که نماینده در بله می‌بیند از اینجا قابل تغییر است.
                فیلد خالی یعنی «متن پیش‌فرض».
            </p>
        </div>

        <form action="{{ route('admin.bot-texts.reset') }}" method="POST"
              onsubmit="return confirm('همه متن‌ها به حالت پیش‌فرض برمی‌گردند. مطمئن هستید؟')">
            @csrf
            <button type="submit"
                    class="py-2 px-4 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-700 shadow-sm hover:bg-gray-50">
                بازگرداندن همه به پیش‌فرض
            </button>
        </form>
    </div>

    <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
        <p class="font-medium mb-1">راهنمای متغیرها</p>
        <p class="text-xs leading-6">
            هر جا زیر یک فیلد متغیری مثل <code class="px-1 rounded bg-white/70" dir="ltr">{name}</code> دیدی،
            می‌توانی آن را داخل متن بگذاری تا هنگام ارسال با مقدار واقعی جایگزین شود.
            روی هر متغیر کلیک کنی، در همان فیلد درج می‌شود.
            برای متن پررنگ <code class="px-1 rounded bg-white/70" dir="ltr">*متن*</code> و
            برای مورب <code class="px-1 rounded bg-white/70" dir="ltr">_متن_</code> بنویس.
        </p>
    </div>

    <form action="{{ route('admin.bot-texts.update') }}" method="POST">
        @csrf

        <div class="space-y-6">
            @foreach($catalog as $groupKey => $group)
                <div class="bg-white border rounded-xl shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-3 border-b border-gray-100">
                        {{ $group['label'] }}
                    </h3>

                    <div class="grid gap-5 sm:grid-cols-2">
                        @foreach($group['items'] as $key => $def)
                            @php
                                $current  = $values[$key] ?? $def['default'];
                                $isCustom = $current !== $def['default'];
                                $rows     = $def['rows'] ?? 2;
                            @endphp

                            <div class="{{ $rows > 2 ? 'sm:col-span-2' : '' }}">
                                <label for="text_{{ $key }}" class="flex items-center gap-2 text-sm font-medium mb-2">
                                    <span>{{ $def['label'] }}</span>
                                    @if($isCustom)
                                        <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-green-100 text-green-700">سفارشی</span>
                                    @endif
                                </label>

                                @if($rows === 1)
                                    <input type="text"
                                           id="text_{{ $key }}"
                                           name="texts[{{ $key }}]"
                                           value="{{ old('texts.'.$key, $current) }}"
                                           placeholder="{{ $def['default'] }}"
                                           class="js-bot-text py-2.5 px-4 block w-full border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                                @else
                                    <textarea id="text_{{ $key }}"
                                              name="texts[{{ $key }}]"
                                              rows="{{ $rows }}"
                                              placeholder="{{ $def['default'] }}"
                                              class="js-bot-text py-2.5 px-4 block w-full border border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">{{ old('texts.'.$key, $current) }}</textarea>
                                @endif

                                @if(!empty($def['vars']))
                                    <div class="flex flex-wrap items-center gap-1.5 mt-2">
                                        <span class="text-xs text-gray-400">متغیرها:</span>
                                        @foreach($def['vars'] as $var)
                                            <button type="button"
                                                    onclick="insertVar('text_{{ $key }}', '{{ '{'.$var.'}' }}')"
                                                    class="text-[11px] px-2 py-0.5 rounded-md bg-gray-100 text-gray-600 hover:bg-blue-100 hover:text-blue-700 transition-colors"
                                                    dir="ltr">{{ '{'.$var.'}' }}</button>
                                        @endforeach
                                    </div>
                                @endif

                                @error('texts.'.$key)
                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="sticky bottom-0 mt-6 -mx-4 px-4 py-3 bg-white/90 backdrop-blur border-t border-gray-200">
            <button type="submit"
                    class="py-2.5 px-5 inline-flex items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-green-600 text-white hover:bg-green-700">
                ذخیره متن‌ها
            </button>
        </div>
    </form>

    <script>
        function insertVar(fieldId, token) {
            const el = document.getElementById(fieldId);
            if (!el) return;

            const start = el.selectionStart ?? el.value.length;
            const end   = el.selectionEnd ?? el.value.length;

            el.value = el.value.slice(0, start) + token + el.value.slice(end);
            el.focus();
            el.selectionStart = el.selectionEnd = start + token.length;
        }
    </script>
@endsection
