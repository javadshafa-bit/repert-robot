<?php

namespace App\Support;

use Morilog\Jalali\Jalalian;

/**
 * تقویم شمسی برای انتخاب تاریخ با دکمه‌های inline در ربات.
 *
 * چون بله انتخابگر تاریخ بومی ندارد، انتخاب در سه مرحله انجام می‌شود:
 * سال ← ماه ← روز. هر مرحله یک صفحه‌کلید inline است و callback_data
 * وضعیت را حمل می‌کند (بدون نیاز به ذخیره‌ی حالت میانی در دیتابیس):
 *
 *   caly_<page>            → صفحه‌ی گرید سال‌ها
 *   calm_<year>            → انتخاب ماه برای این سال
 *   cald_<year>_<month>    → انتخاب روز برای این سال و ماه
 *   calp_<year>_<m>_<d>    → تاریخ نهایی انتخاب شد
 *
 * محدوده‌ی مجاز از روی date_range فیلد تعیین می‌شود:
 *   past   → تا امروز
 *   future → از امروز به بعد
 *   any    → بدون محدودیت
 */
class JalaliCalendar
{
    /** پایین‌ترین سالی که کاربر می‌تواند به آن برسد */
    public const MIN_YEAR = 1300;

    /** چند سال جلوتر از امسال قابل انتخاب است */
    public const FUTURE_YEARS = 5;

    /** تعداد سال در هر صفحه‌ی گرید سال (۴ ستون × ۶ ردیف) */
    public const YEARS_PER_PAGE = 24;

    public const MONTH_NAMES = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد',
        4 => 'تیر',     5 => 'مرداد',    6 => 'شهریور',
        7 => 'مهر',     8 => 'آبان',     9 => 'آذر',
        10 => 'دی',     11 => 'بهمن',    12 => 'اسفند',
    ];

    // ==========================================
    // مرزهای مجاز
    // ==========================================

    /** [سال, ماه, روز] امروز */
    public static function today(): array
    {
        $now = Jalalian::now();
        return [$now->getYear(), $now->getMonth(), $now->getDay()];
    }

    public static function maxYear(string $range): int
    {
        [$y] = self::today();
        return $range === 'past' ? $y : $y + self::FUTURE_YEARS;
    }

    public static function minYear(string $range): int
    {
        [$y] = self::today();
        return $range === 'future' ? $y : self::MIN_YEAR;
    }

    /** آیا این تاریخ داخل محدوده‌ی مجاز است؟ */
    public static function inRange(int $year, int $month, int $day, string $range): bool
    {
        if ($range === 'any') {
            return true;
        }

        [$ty, $tm, $td] = self::today();
        $value = $year * 10000 + $month * 100 + $day;
        $today = $ty * 10000 + $tm * 100 + $td;

        return $range === 'past' ? $value <= $today : $value >= $today;
    }

    // ==========================================
    // منطق تقویم
    // ==========================================

    /** سال کبیسه‌ی شمسی بر پایه‌ی الگوریتم ۳۳ساله (همان که Jalalian استفاده می‌کند) */
    public static function isLeapYear(int $year): bool
    {
        $breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210,
                   1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];

        $jp = $breaks[0];
        $jump = 0;

        for ($i = 1; $i < count($breaks); $i++) {
            $jm = $breaks[$i];
            $jump = $jm - $jp;
            if ($year < $jm) {
                break;
            }
            $jp = $jm;
        }

        $n = $year - $jp;

        if ($jump - $n < 6) {
            $n = $n - $jump + (int) (($jump + 4) / 33) * 33;
        }

        $leap = ((($n + 1) % 33) - 1) % 4;
        if ($leap === -1) {
            $leap = 4;
        }

        return $leap === 0;
    }

    /** تعداد روزهای یک ماه شمسی */
    public static function daysInMonth(int $year, int $month): int
    {
        if ($month <= 6)  return 31;
        if ($month <= 11) return 30;
        return self::isLeapYear($year) ? 30 : 29;
    }

    /** اعتبارسنجی کامل یک تاریخ شمسی */
    public static function isValid(int $year, int $month, int $day): bool
    {
        if ($year < self::MIN_YEAR || $year > 1600) return false;
        if ($month < 1 || $month > 12)              return false;

        return $day >= 1 && $day <= self::daysInMonth($year, $month);
    }

    /** «۱۴۰۵/۰۶/۰۹» */
    public static function format(int $year, int $month, int $day): string
    {
        return sprintf('%04d/%02d/%02d', $year, $month, $day);
    }

    /** «۹ شهریور ۱۴۰۵» */
    public static function formatLong(int $year, int $month, int $day): string
    {
        return $day . ' ' . (self::MONTH_NAMES[$month] ?? '') . ' ' . $year;
    }

    // ==========================================
    // صفحه‌کلیدها
    // ==========================================

    /**
     * گرید سال‌ها. صفحه ۰ جدیدترین سال‌هاست؛ صفحه‌های بعدی عقب‌تر می‌روند.
     * دکمه‌های «سال‌های قدیمی‌تر / جدیدتر» فقط وقتی نمایش داده می‌شوند که چیزی آن طرف باشد.
     */
    public static function yearKeyboard(string $range, int $page = 0): array
    {
        $max = self::maxYear($range);
        $min = self::minYear($range);

        $start = $max - ($page * self::YEARS_PER_PAGE);
        $end   = max($min, $start - self::YEARS_PER_PAGE + 1);

        $years = [];
        for ($y = $start; $y >= $end; $y--) {
            $years[] = $y;
        }

        $rows = array_map(
            fn($chunk) => array_map(fn($y) => ['text' => (string) $y, 'callback_data' => "calm_$y"], $chunk),
            array_chunk($years, 4)
        );

        $nav = [];
        if ($end > $min) {
            $nav[] = ['text' => '« قدیمی‌تر', 'callback_data' => 'caly_' . ($page + 1)];
        }
        if ($page > 0) {
            $nav[] = ['text' => 'جدیدتر »', 'callback_data' => 'caly_' . ($page - 1)];
        }
        if ($nav) {
            $rows[] = $nav;
        }

        $rows[] = [['text' => BotText::get('btn_back'), 'callback_data' => 'go_back']];

        return ['inline_keyboard' => $rows];
    }

    /** ۱۲ ماه در ۴ ردیف؛ ماه‌های خارج از محدوده حذف می‌شوند */
    public static function monthKeyboard(int $year, string $range): array
    {
        $buttons = [];
        foreach (self::MONTH_NAMES as $num => $name) {
            // اگر هیچ روزی از این ماه مجاز نباشد، ماه را نشان نده
            if (!self::monthHasAnyValidDay($year, $num, $range)) {
                continue;
            }
            $buttons[] = ['text' => $name, 'callback_data' => "cald_{$year}_{$num}"];
        }

        $rows = array_chunk($buttons, 3);
        $rows[] = [
            ['text' => 'تغییر سال', 'callback_data' => 'caly_0'],
            ['text' => BotText::get('btn_back'), 'callback_data' => 'go_back'],
        ];

        return ['inline_keyboard' => $rows];
    }

    /** گرید روزها؛ روزهای خارج از محدوده حذف می‌شوند */
    public static function dayKeyboard(int $year, int $month, string $range): array
    {
        $buttons = [];
        $days    = self::daysInMonth($year, $month);

        for ($d = 1; $d <= $days; $d++) {
            if (!self::inRange($year, $month, $d, $range)) {
                continue;
            }
            $buttons[] = ['text' => (string) $d, 'callback_data' => "calp_{$year}_{$month}_{$d}"];
        }

        $rows = array_chunk($buttons, 7);
        $rows[] = [
            ['text' => 'تغییر ماه', 'callback_data' => "calm_$year"],
            ['text' => BotText::get('btn_back'), 'callback_data' => 'go_back'],
        ];

        return ['inline_keyboard' => $rows];
    }

    /** آیا این ماه دست‌کم یک روز مجاز دارد؟ */
    private static function monthHasAnyValidDay(int $year, int $month, string $range): bool
    {
        if ($range === 'any') {
            return true;
        }

        $days = self::daysInMonth($year, $month);

        // برای past اولین روز و برای future آخرین روز کافی است
        return $range === 'past'
            ? self::inRange($year, $month, 1, $range)
            : self::inRange($year, $month, $days, $range);
    }
}
