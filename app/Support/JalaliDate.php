<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;

/**
 * تبدیل تاریخ شمسیِ واردشده در فرم‌ها به Carbon و برعکس.
 *
 * ورودی مورد انتظار: `1405/06/03` (ارقام فارسی و جداکننده‌ی - یا . هم پذیرفته می‌شود).
 * ذخیره‌سازی همیشه UTC است؛ مرز روز بر اساس ساعت تهران حساب می‌شود.
 */
class JalaliDate
{
    public const TIMEZONE = 'Asia/Tehran';

    /** @throws InvalidArgumentException وقتی رشته تاریخ شمسی معتبری نیست */
    public static function parse(?string $value, bool $endOfDay = false): ?Carbon
    {
        $value = self::normalizeDigits(trim((string) $value));

        if ($value === '') {
            return null;
        }

        $value = str_replace(['-', '.'], '/', $value);

        if (!preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $value, $matches)) {
            throw new InvalidArgumentException('فرمت تاریخ باید مثل 1405/06/03 باشد.');
        }

        [, $year, $month, $day] = array_map('intval', $matches);

        if (!CalendarUtils::checkDate($year, $month, $day, true)) {
            throw new InvalidArgumentException('تاریخ شمسی واردشده معتبر نیست.');
        }

        [$gy, $gm, $gd] = CalendarUtils::toGregorian($year, $month, $day);

        $carbon = Carbon::create($gy, $gm, $gd, 0, 0, 0, self::TIMEZONE);

        return ($endOfDay ? $carbon->endOfDay() : $carbon->startOfDay())->utc();
    }

    public static function format(?Carbon $date): ?string
    {
        return $date === null
            ? null
            : Jalalian::fromCarbon($date->copy()->setTimezone(self::TIMEZONE))->format('Y/m/d');
    }

    /** ارقام فارسی/عربی → لاتین */
    public static function normalizeDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}
