<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;

class BroadcastMessage extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'title', 'body', 'photo_path', 'province_ids',
        'schedule_type', 'scheduled_at', 'send_time', 'day_of_week', 'jalali_day',
        'last_sent_date', 'status', 'sent_count', 'failed_count', 'last_sent_at', 'created_by',
    ];

    protected $casts = [
        'province_ids'   => 'array',
        'scheduled_at'   => 'datetime',
        'last_sent_at'   => 'datetime',
        'last_sent_date' => 'date',
    ];

    public const TIMEZONE = 'Asia/Tehran';

    /** روزهای هفته شمسی: 0=شنبه ... 6=جمعه */
    public const WEEKDAYS = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** نمایندگان گیرنده (متصل به ربات + فیلتر استان) */
    public function recipientsQuery()
    {
        $q = Representative::whereNotNull('chat_id')->where('is_connected', true);
        if (!empty($this->province_ids)) {
            $q->whereIn('province_id', $this->province_ids);
        }
        return $q;
    }

    /** آیا الان موعد ارسال است؟ */
    public function isDue(): bool
    {
        $nowTehran = Carbon::now(self::TIMEZONE);

        if ($this->schedule_type === 'once') {
            return $this->status === 'pending'
                && $this->scheduled_at !== null
                && $this->scheduled_at->lte(Carbon::now());
        }

        if (!in_array($this->schedule_type, ['weekly', 'monthly_jalali']) || $this->status !== 'active') {
            return false;
        }

        // در هر روز فقط یک‌بار
        if ($this->last_sent_date && $this->last_sent_date->format('Y-m-d') === $nowTehran->format('Y-m-d')) {
            return false;
        }

        // ساعت ارسال رسیده باشد (اگر cron دیر اجرا شود، باز هم ارسال می‌شود)
        if ($this->send_time && $nowTehran->format('H:i') < $this->send_time) {
            return false;
        }

        if ($this->schedule_type === 'weekly') {
            // Carbon: 0=یکشنبه..6=شنبه → شمسی: 0=شنبه..6=جمعه
            $persianDow = ($nowTehran->dayOfWeek + 1) % 7;
            return $persianDow === (int) $this->day_of_week;
        }

        // monthly_jalali
        $jNow        = Jalalian::fromCarbon($nowTehran);
        $daysInMonth = CalendarUtils::jalaliMonthLength($jNow->getYear(), $jNow->getMonth());
        $targetDay   = min((int) $this->jalali_day, $daysInMonth); // اسفند ۲۹/۳۰ روزه
        return $jNow->getDay() === $targetDay;
    }

    /** تبدیل تاریخ‌وساعت شمسی (به وقت تهران) به Carbon با تایم‌زون اپ */
    public static function jalaliToAppTime(int $jy, int $jm, int $jd, string $time): Carbon
    {
        [$gy, $gm, $gd] = CalendarUtils::toGregorian($jy, $jm, $jd);
        return Carbon::parse(sprintf('%04d-%02d-%02d %s', $gy, $gm, $gd, $time), self::TIMEZONE)
            ->setTimezone(config('app.timezone'));
    }

    /** نمایش زمان‌بندی به‌صورت متن فارسی */
    public function getScheduleLabelAttribute(): string
    {
        return match ($this->schedule_type) {
            'instant'        => 'ارسال فوری',
            'once'           => $this->scheduled_at
                ? 'یک‌بار — ' . Jalalian::fromCarbon($this->scheduled_at->copy()->setTimezone(self::TIMEZONE))->format('Y/m/d ساعت H:i')
                : 'یک‌بار',
            'weekly'         => 'هر هفته ' . (self::WEEKDAYS[$this->day_of_week] ?? '') . ' ساعت ' . $this->send_time,
            'monthly_jalali' => 'هر ماه شمسی، روز ' . $this->jalali_day . ' ساعت ' . $this->send_time,
            default          => $this->schedule_type,
        };
    }
}
