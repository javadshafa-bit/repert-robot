@extends('layouts.app')

@section('content')
    <div class="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">پیام همگانی</h2>
            <p class="text-sm text-gray-600 mt-1">ارسال پیام به نمایندگان متصل به ربات — در حال حاضر {{ $connectedCount }} نماینده متصل است</p>
        </div>
        <a href="{{ route('admin.broadcasts.create') }}"
           class="py-2 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700">
            <svg class="size-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14"/><path d="M12 5v14"/>
            </svg>
            پیام جدید
        </a>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">عنوان</th>
                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">زمان‌بندی</th>
                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">گیرندگان</th>
                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase">آمار ارسال</th>
                <th class="px-6 py-3 text-end   text-xs font-medium text-gray-500 uppercase">عملیات</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
            @forelse($messages as $message)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 text-sm">
                        <div class="font-semibold text-gray-800 flex items-center gap-x-2">
                            {{ $message->title }}
                            @if($message->photo_path)
                                <svg class="size-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500 mt-1 max-w-xs truncate">{{ \Illuminate\Support\Str::limit($message->body, 80) }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $message->schedule_label }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                        @if(empty($message->province_ids))
                            همه استان‌ها
                        @else
                            {{ count($message->province_ids) }} استان
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @switch($message->status)
                            @case('sent')
                                <span class="inline-flex items-center gap-1 py-0.5 px-2 rounded-md text-xs font-medium bg-green-100 text-green-700"><span class="size-1.5 rounded-full bg-green-500"></span> ارسال‌شده</span>
                                @break
                            @case('pending')
                                <span class="inline-flex items-center gap-1 py-0.5 px-2 rounded-md text-xs font-medium bg-yellow-100 text-yellow-700"><span class="size-1.5 rounded-full bg-yellow-500"></span> در انتظار</span>
                                @break
                            @case('active')
                                <span class="inline-flex items-center gap-1 py-0.5 px-2 rounded-md text-xs font-medium bg-blue-100 text-blue-700"><span class="size-1.5 rounded-full bg-blue-500"></span> فعال</span>
                                @break
                            @case('paused')
                                <span class="inline-flex items-center gap-1 py-0.5 px-2 rounded-md text-xs font-medium bg-gray-100 text-gray-600"><span class="size-1.5 rounded-full bg-gray-400"></span> متوقف</span>
                                @break
                            @case('canceled')
                                <span class="inline-flex items-center gap-1 py-0.5 px-2 rounded-md text-xs font-medium bg-red-100 text-red-700"><span class="size-1.5 rounded-full bg-red-500"></span> لغوشده</span>
                                @break
                        @endswitch
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                        @if($message->last_sent_at)
                            <span class="text-green-600 font-medium">{{ $message->sent_count }} موفق</span>
                            @if($message->failed_count)
                                / <span class="text-red-500">{{ $message->failed_count }} ناموفق</span>
                            @endif
                            <div class="text-xs text-gray-400 mt-0.5">
                                آخرین ارسال: {{ \Morilog\Jalali\Jalalian::fromCarbon($message->last_sent_at->copy()->setTimezone('Asia/Tehran'))->format('Y/m/d H:i') }}
                            </div>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-end text-sm">
                        <div class="inline-flex items-center gap-x-3">
                            @if(in_array($message->schedule_type, ['weekly', 'monthly_jalali']))
                                <form action="{{ route('admin.broadcasts.toggle', $message) }}" method="POST" class="inline-block">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="text-amber-600 hover:text-amber-800 font-medium transition-colors">
                                        {{ $message->status === 'active' ? 'توقف' : 'فعال‌سازی' }}
                                    </button>
                                </form>
                            @elseif($message->schedule_type === 'once' && $message->status === 'pending')
                                <form action="{{ route('admin.broadcasts.toggle', $message) }}" method="POST" class="inline-block"
                                      onsubmit="return confirm('پیام زمان‌بندی‌شده لغو شود؟');">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="text-amber-600 hover:text-amber-800 font-medium transition-colors">لغو ارسال</button>
                                </form>
                            @endif
                            <form action="{{ route('admin.broadcasts.destroy', $message) }}" method="POST" class="inline-block"
                                  onsubmit="return confirm('این پیام حذف شود؟');">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-700 font-medium transition-colors">حذف</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-6 py-10 text-center text-gray-400">هنوز پیامی ثبت نشده است.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($messages->hasPages())
        <div class="mt-4">{{ $messages->links() }}</div>
    @endif
@endsection
