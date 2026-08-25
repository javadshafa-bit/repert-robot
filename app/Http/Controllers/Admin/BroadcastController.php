<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BroadcastMessage;
use App\Models\Province;
use App\Models\Representative;
use App\Services\BroadcastSender;
use App\Support\TenantContext;
use App\Support\TenantRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;

class BroadcastController extends Controller
{
    public function index()
    {
        $messages = BroadcastMessage::with('creator')->latest()->paginate(15);
        $connectedCount = Representative::whereNotNull('chat_id')->where('is_connected', true)->count();

        return view('admin.broadcasts.index', compact('messages', 'connectedCount'));
    }

    public function create()
    {
        $provinces = Province::orderBy('name')->get();
        $jNow      = Jalalian::fromCarbon(\Carbon\Carbon::now(BroadcastMessage::TIMEZONE));

        return view('admin.broadcasts.create', compact('provinces', 'jNow'));
    }

    public function store(Request $request, BroadcastSender $sender)
    {
        $request->validate([
            'title'         => 'required|string|max:255',
            'body'          => 'required|string|max:4000',
            'photo'         => 'nullable|image|max:5120',
            'province_ids'  => 'nullable|array',
            'province_ids.*'=> [TenantRule::exists('provinces')],
            'schedule_type' => 'required|in:instant,once,weekly,monthly_jalali',
            // once
            'jalali_year'   => 'required_if:schedule_type,once|nullable|integer|min:1400|max:1500',
            'jalali_month'  => 'required_if:schedule_type,once|nullable|integer|min:1|max:12',
            'jalali_date'   => 'required_if:schedule_type,once|nullable|integer|min:1|max:31',
            // recurring
            'send_time'     => 'required_unless:schedule_type,instant|nullable|date_format:H:i',
            'day_of_week'   => 'required_if:schedule_type,weekly|nullable|integer|min:0|max:6',
            'jalali_day'    => 'required_if:schedule_type,monthly_jalali|nullable|integer|min:1|max:31',
        ], [], [
            'title' => 'عنوان', 'body' => 'متن پیام', 'photo' => 'عکس',
            'send_time' => 'ساعت ارسال', 'day_of_week' => 'روز هفته', 'jalali_day' => 'روز ماه',
            'jalali_year' => 'سال', 'jalali_month' => 'ماه', 'jalali_date' => 'روز',
        ]);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('broadcasts/' . TenantContext::id(), 'public');
        }

        $type = $request->schedule_type;
        $data = [
            'title'        => $request->title,
            'body'         => $request->body,
            'photo_path'   => $photoPath,
            'province_ids' => $request->filled('province_ids') ? array_map('intval', $request->province_ids) : null,
            'schedule_type'=> $type,
            'created_by'   => $request->user()->id,
        ];

        if ($type === 'once') {
            $daysInMonth = CalendarUtils::jalaliMonthLength((int) $request->jalali_year, (int) $request->jalali_month);
            if ((int) $request->jalali_date > $daysInMonth) {
                return back()->withInput()->withErrors(['jalali_date' => "این ماه فقط {$daysInMonth} روز دارد."]);
            }
            $scheduledAt = BroadcastMessage::jalaliToAppTime(
                (int) $request->jalali_year, (int) $request->jalali_month, (int) $request->jalali_date,
                $request->send_time
            );
            if ($scheduledAt->isPast()) {
                return back()->withInput()->withErrors(['jalali_date' => 'زمان انتخاب‌شده گذشته است.']);
            }
            $data += ['scheduled_at' => $scheduledAt, 'send_time' => $request->send_time, 'status' => 'pending'];
        } elseif ($type === 'weekly') {
            $data += ['send_time' => $request->send_time, 'day_of_week' => (int) $request->day_of_week, 'status' => 'active'];
        } elseif ($type === 'monthly_jalali') {
            $data += ['send_time' => $request->send_time, 'jalali_day' => (int) $request->jalali_day, 'status' => 'active'];
        } else {
            $data += ['status' => 'pending'];
        }

        $message = BroadcastMessage::create($data);

        if ($type === 'instant') {
            $sender->send($message);
            return redirect()->route('admin.broadcasts.index')
                ->with('success', "پیام به {$message->sent_count} نماینده ارسال شد" . ($message->failed_count ? " ({$message->failed_count} ناموفق)" : '') . '.');
        }

        return redirect()->route('admin.broadcasts.index')->with('success', 'پیام زمان‌بندی شد.');
    }

    /** توقف/فعال‌سازی پیام تکرارشونده یا لغو پیام زمان‌بندی‌شده */
    public function toggle(BroadcastMessage $broadcast)
    {
        if (in_array($broadcast->schedule_type, ['weekly', 'monthly_jalali'])) {
            $broadcast->update(['status' => $broadcast->status === 'active' ? 'paused' : 'active']);
        } elseif ($broadcast->schedule_type === 'once' && $broadcast->status === 'pending') {
            $broadcast->update(['status' => 'canceled']);
        }
        return back()->with('success', 'وضعیت پیام به‌روزرسانی شد.');
    }

    public function destroy(BroadcastMessage $broadcast)
    {
        if ($broadcast->photo_path) {
            Storage::disk('public')->delete($broadcast->photo_path);
        }
        $broadcast->delete();
        return back()->with('success', 'پیام حذف شد.');
    }
}
