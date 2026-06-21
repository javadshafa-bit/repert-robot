@extends('layouts.app')
@section('content')
<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-800">ویرایش نقش: {{ $role->label }}</h2>
    <a href="{{ route('admin.roles.index') }}" class="text-sm text-blue-600 hover:underline">← بازگشت</a>
</div>

<form action="{{ route('admin.roles.update', $role) }}" method="POST" class="max-w-2xl space-y-6">
    @csrf @method('PUT')

    <div class="bg-white border rounded-xl shadow-sm p-6 space-y-4">
        <h3 class="font-semibold text-gray-700 border-b pb-2">مشخصات نقش</h3>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">نام نمایشی <span class="text-red-500">*</span></label>
                <input type="text" name="label" value="{{ old('label', $role->label) }}"
                       class="py-2 px-3 w-full border border-gray-300 rounded-lg text-sm" required>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">شناسه</label>
                <input type="text" value="{{ $role->name }}"
                       class="py-2 px-3 w-full border border-gray-200 rounded-lg text-sm font-mono bg-gray-50 text-gray-400"
                       disabled dir="ltr">
            </div>
        </div>
    </div>

    {{-- دسترسی‌ها --}}
    <div class="bg-white border rounded-xl shadow-sm p-6">
        <h3 class="font-semibold text-gray-700 border-b pb-2 mb-4">دسترسی‌های این نقش</h3>
        <div class="grid sm:grid-cols-2 gap-3">
            @foreach($permissions as $key => $label)
            @php $checked = in_array($key, old('permissions', $role->permissions ?? [])); @endphp
            <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-blue-50 {{ $checked ? 'border-blue-400 bg-blue-50' : '' }}">
                <input type="checkbox" name="permissions[]" value="{{ $key }}"
                       {{ $checked ? 'checked' : '' }}
                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm text-gray-700">{{ $label }}</span>
            </label>
            @endforeach
        </div>
    </div>

    {{-- محدوده دپارتمان --}}
    <div class="bg-white border rounded-xl shadow-sm p-6">
        <h3 class="font-semibold text-gray-700 border-b pb-2 mb-4">محدوده مشاهده گزارش‌ها</h3>
        @php $allDepts = old('all_departments') !== null ? old('all_departments') : $role->all_departments; @endphp
        <label class="flex items-center gap-3 cursor-pointer mb-4">
            <input type="checkbox" name="all_departments" id="all_departments" value="1"
                   {{ $allDepts ? 'checked' : '' }}
                   class="rounded border-gray-300 text-green-600 focus:ring-green-500"
                   onchange="document.getElementById('dept-select').classList.toggle('hidden', this.checked)">
            <span class="text-sm font-medium text-gray-700">دسترسی به همه دپارتمان‌ها</span>
        </label>
        <div id="dept-select" class="{{ $allDepts ? 'hidden' : '' }}">
            <p class="text-xs text-gray-500 mb-3">دپارتمان‌های مجاز:</p>
            <div class="grid sm:grid-cols-2 gap-2">
                @php $selectedDepts = old('departments', $role->departments->pluck('id')->toArray()); @endphp
                @foreach($departments as $dept)
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="departments[]" value="{{ $dept->id }}"
                           {{ in_array($dept->id, $selectedDepts) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-blue-600">
                    <span class="text-sm">{{ $dept->name }}</span>
                </label>
                @endforeach
            </div>
        </div>
    </div>

    <button type="submit" class="py-2 px-6 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700">
        ذخیره تغییرات
    </button>
</form>
@endsection
