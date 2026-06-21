@extends('layouts.app')
@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-gray-800">مدیریت کاربران</h2>
        <p class="text-sm text-gray-500 mt-1">ساخت و مدیریت حساب‌های کاربری پنل مدیریت</p>
    </div>
    <a href="{{ route('admin.users.create') }}"
       class="py-2 px-4 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700">
        + افزودن کاربر
    </a>
</div>

<div class="bg-white border rounded-xl shadow-sm overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-5 py-3 text-start text-xs font-medium text-gray-500">نام</th>
                <th class="px-5 py-3 text-start text-xs font-medium text-gray-500">ایمیل</th>
                <th class="px-5 py-3 text-start text-xs font-medium text-gray-500">نقش‌ها</th>
                <th class="px-5 py-3 text-start text-xs font-medium text-gray-500">سطح</th>
                <th class="px-5 py-3 text-end   text-xs font-medium text-gray-500">عملیات</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
        @foreach($users as $user)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-4 text-sm font-medium text-gray-800">
                    {{ $user->name }}
                    @if($user->id === auth()->id())
                        <span class="text-xs text-gray-400">(شما)</span>
                    @endif
                </td>
                <td class="px-5 py-4 text-sm text-gray-500" dir="ltr">{{ $user->email }}</td>
                <td class="px-5 py-4">
                    <div class="flex flex-wrap gap-1">
                    @forelse($user->roles as $role)
                        <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700">{{ $role->label }}</span>
                    @empty
                        <span class="text-xs text-gray-400">—</span>
                    @endforelse
                    </div>
                </td>
                <td class="px-5 py-4">
                    @if($user->is_super_admin)
                        <span class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-semibold">سوپر ادمین</span>
                    @else
                        <span class="text-xs text-gray-400">عادی</span>
                    @endif
                </td>
                <td class="px-5 py-4 text-end text-sm flex gap-3 justify-end">
                    <a href="{{ route('admin.users.edit', $user) }}" class="text-blue-600 hover:underline font-medium">ویرایش</a>
                    @if($user->id !== auth()->id())
                    <form action="{{ route('admin.users.destroy', $user) }}" method="POST"
                          onsubmit="return confirm('این کاربر حذف شود؟');">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-500 hover:underline">حذف</button>
                    </form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
