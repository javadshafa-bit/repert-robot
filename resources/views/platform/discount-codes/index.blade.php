@extends('layouts.platform')

@section('title', 'کدهای تخفیف')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-slate-800">کدهای تخفیف</h1>
        <a href="{{ route('platform.discount-codes.create') }}"
           class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm hover:bg-slate-800">کد جدید</a>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl overflow-x-auto">
        <table class="w-full text-sm text-right whitespace-nowrap">
            <thead class="bg-slate-50 text-slate-600">
            <tr>
                <th class="px-4 py-3 font-medium">کد</th>
                <th class="px-4 py-3 font-medium">درصد</th>
                <th class="px-4 py-3 font-medium">مصرف</th>
                <th class="px-4 py-3 font-medium">بازه‌ی اعتبار</th>
                <th class="px-4 py-3 font-medium">وضعیت</th>
                <th class="px-4 py-3 font-medium">عملیات</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($codes as $code)
                <tr>
                    <td class="px-4 py-3 font-mono" dir="ltr">{{ $code->code }}</td>
                    <td class="px-4 py-3">{{ $code->percent }}٪</td>
                    <td class="px-4 py-3">{{ number_format($code->used_count) }} از {{ $code->max_uses ? number_format($code->max_uses) : 'نامحدود' }}</td>
                    <td class="px-4 py-3 text-slate-500">
                        {{ \App\Support\JalaliDate::format($code->starts_at) ?: 'از همین حالا' }}
                        تا
                        {{ \App\Support\JalaliDate::format($code->expires_at) ?: 'بدون انقضا' }}
                    </td>
                    <td class="px-4 py-3">
                        @php $reason = $code->invalidReason(); @endphp
                        @if($reason === null)
                            <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-800">قابل استفاده</span>
                        @else
                            <span class="px-2 py-1 rounded text-xs bg-slate-100 text-slate-600">{{ $reason }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('platform.discount-codes.edit', $code) }}" class="text-blue-600 hover:underline text-xs">ویرایش</a>

                            <form action="{{ route('platform.discount-codes.toggle', $code) }}" method="POST">
                                @csrf @method('PATCH')
                                <button class="text-amber-600 hover:underline text-xs">{{ $code->is_active ? 'غیرفعال کردن' : 'فعال کردن' }}</button>
                            </form>

                            @if($code->used_count === 0)
                                <form action="{{ route('platform.discount-codes.destroy', $code) }}" method="POST">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 hover:underline text-xs">حذف</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">هنوز کدی ساخته نشده است.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $codes->links() }}</div>
@endsection
