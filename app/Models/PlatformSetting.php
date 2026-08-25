<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * تنظیمات سطح پلتفرم — عمداً بدون tenant_id و جدا از جدول `settings` (که per-tenant است).
 *
 * همه‌ی مبالغ به **تومان** ذخیره می‌شوند.
 */
class PlatformSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public $timestamps = true;

    /** مقادیر پیش‌فرض تا وقتی سوپرادمین چیزی ثبت نکرده */
    public const DEFAULTS = [
        'price_per_day'        => 5000,     // تومان به ازای هر روز اشتراک
        'min_payment_amount'   => 50000,    // حداقل مبلغ قابل پرداخت (تومان)
        'zarinpal_merchant_id' => '',
        'zarinpal_sandbox'     => '1',      // تا وقتی merchant_id واقعی نداریم
    ];

    /** کش درون‌درخواستی؛ عمداً کش دائمی نیست تا تغییر سوپرادمین بلافاصله اثر کند */
    private static ?array $cache = null;

    public static function all_values(): array
    {
        if (self::$cache === null) {
            self::$cache = static::query()->pluck('value', 'key')->all();
        }

        return array_merge(self::DEFAULTS, self::$cache);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all_values()[$key] ?? $default ?? self::DEFAULTS[$key] ?? null;
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    public static function bool(string $key): bool
    {
        return filter_var(self::get($key), FILTER_VALIDATE_BOOLEAN);
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        self::$cache = null;
    }

    public static function forgetCache(): void
    {
        self::$cache = null;
    }
}
