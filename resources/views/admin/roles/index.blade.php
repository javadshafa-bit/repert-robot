@extends('layouts.app')
@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-gray-800">نقش‌ها و دسترسی‌ها</h2>
        <p class="text-sm text-gray-500 mt-1">تعریف نقش‌ها و تعیین سطح دسترسی برای هر نقش</p>
    </div>
    <a href="{{ route('admin.roles.create') }}"
       class="py-2 px-4 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700">
        + افزودن نقش جدید
    </a>
</div>

<div class="bg-white border rounded-xl shadow-sm overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-5 py-3 text-start text-xs font-medium text-gray-500">نام نقش</th>
                <th class="px-5 py-3 text-start text-xs font-medium text-gray-500">شناسه</th>
                <th class="px-5 py-3 text-start text-xs font-medium text-gray-500">دسترسی‌ها</th>
                <th class="px-5 py-3 text-start text-xs font-medium text-gray-500">محدوده دپارتمان</th>
                <th class="px-5 py-3 text-start text-xs font-medium text-gray-500">کاربران</th>
                <th class="px-5 py-3 text-end   text-xs font-medium text-gray-500">عملیات</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
        @forelse($roles as $role)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-4 text-sm font-semibold text-gray-800">{{ $role->label }}</td>
                <td class="px-5 py-4 text-xs text-gray-400 font-mono">{{ $role->name }}</td>
                <td class="px-5 py-4 text-sm">
                    <div class="flex flex-wrap gap-1">
                    @forelse($role->permissions ?? [] as $perm)
                        <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">
                            {{ \App\Models\Role::allPermissions()[$perm] ?? $perm }}
                        </span>
                    @empty
                        <span class="text-xs text-gray-400">—</span>
                    @endforelse
                    </div>
                </td>
                <td class="px-5 py-4 text-sm">
                    @if($role->all_departments)
                        <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">همه دپارتمان‌ها</span>
                    @else
                        <span class="text-xs px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700">محدود</span>
                    @endif
                </td>
                <td class="px-5 py-4 text-sm text-gray-600">{{ $role->users_count }} نفر</td>
                <td class="px-5 py-4 text-end text-sm flex gap-3 justify-end">
                    <a href="{{ route('admin.roles.edit', $role) }}" class="text-blue-600 hover:underline font-medium">ویرایش</a>
                    <form action="{{ route('admin.roles.destroy', $role) }}" method="POST"
                          onsubmit="return confirm('این نقش حذف شود؟ کاربران این نقش تغییری نمی‌کنند.');">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-500 hover:underline">حذف</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-6 py-10 text-center text-gray-400">هیچ نقشی تعریف نشده.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
