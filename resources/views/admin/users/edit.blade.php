@extends('layouts.app')
@section('content')
<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-800">ویرایش کاربر: {{ $user->name }}</h2>
    <a href="{{ route('admin.users.index') }}" class="text-sm text-blue-600 hover:underline">← بازگشت</a>
</div>

<form action="{{ route('admin.users.update', $user) }}" method="POST" class="max-w-xl space-y-5">
    @csrf @method('PUT')

    <div class="bg-white border rounded-xl shadow-sm p-6 space-y-4">
        <div>
            <label class="block text-sm font-medium mb-1">نام <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name', $user->name) }}"
                   class="py-2 px-3 w-full border border-gray-300 rounded-lg text-sm" required>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">ایمیل <span class="text-red-500">*</span></label>
            <input type="email" name="email" value="{{ old('email', $user->email) }}"
                   class="py-2 px-3 w-full border border-gray-300 rounded-lg text-sm" required dir="ltr">
            @error('email')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">رمز عبور جدید
                <span class="text-xs text-gray-400 font-normal">(خالی بگذارید تا تغییر نکند)</span>
            </label>
            <input type="password" name="password"
                   class="py-2 px-3 w-full border border-gray-300 rounded-lg text-sm" dir="ltr">
            @error('password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        @if(auth()->user()->isSuperAdmin())
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="is_super_admin" value="1"
                   {{ old('is_super_admin', $user->is_super_admin) ? 'checked' : '' }}
                   class="rounded border-gray-300 text-red-600">
            <span class="text-sm text-gray-700">سوپر ادمین (دسترسی کامل)</span>
        </label>
        @endif
    </div>

    <div class="bg-white border rounded-xl shadow-sm p-6">
        <h3 class="font-semibold text-gray-700 border-b pb-2 mb-4">نقش‌های تخصیص‌یافته</h3>
        @php $userRoleIds = $user->roles->pluck('id')->toArray(); @endphp
        @forelse($roles as $role)
        <label class="flex items-start gap-3 p-3 border rounded-lg mb-2 cursor-pointer hover:bg-indigo-50 {{ in_array($role->id, old('roles', $userRoleIds)) ? 'border-indigo-400 bg-indigo-50' : '' }}">
            <input type="checkbox" name="roles[]" value="{{ $role->id }}"
                   {{ in_array($role->id, old('roles', $userRoleIds)) ? 'checked' : '' }}
                   class="mt-0.5 rounded border-gray-300 text-indigo-600">
            <div>
                <p class="text-sm font-medium text-gray-800">{{ $role->label }}</p>
                <p class="text-xs text-gray-400">
                    {{ $role->all_departments ? 'همه دپارتمان‌ها' : 'دپارتمان‌های محدود' }}
                    — {{ implode(', ', array_map(fn($p) => \App\Models\Role::allPermissions()[$p] ?? $p, $role->permissions ?? [])) ?: 'بدون دسترسی' }}
                </p>
            </div>
        </label>
        @empty
            <p class="text-sm text-gray-400">هیچ نقشی تعریف نشده.</p>
        @endforelse
    </div>

    <button type="submit" class="py-2 px-6 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700">
        ذخیره تغییرات
    </button>
</form>
@endsection
