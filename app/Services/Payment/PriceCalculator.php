<?php

namespace App\Services\Payment;

use App\Models\DiscountCode;
use App\Models\PlatformSetting;

/**
 * محاسبه‌ی مبلغ و تعداد روز — همیشه سمت سرور.
 *
 * کد تخفیفی که کاربر می‌فرستد فقط یک رشته است؛ درصد و اعتبارش از دیتابیس خوانده می‌شود.
 *
 * تصمیم مهم: **تعداد روز از مبلغ پیش از تخفیف حساب می‌شود.**
 * یعنی کد تخفیف ۲۰٪ روی خرید ۳۰ روز، همان ۳۰ روز را ۲۰٪ ارزان‌تر می‌دهد و
 * روزها را کم نمی‌کند (وگرنه تخفیف عملاً بی‌اثر می‌شد).
 */
class PriceCalculator
{
    /**
     * کف مبلغ قابل ارسال به درگاه (تومان) — زیر این مقدار درگاه تراکنش نمی‌سازد.
     * استثنا: مبلغ دقیقاً صفر (تخفیف ۱۰۰٪) که اصلاً به درگاه نمی‌رود.
     */
    public const GATEWAY_MINIMUM = 1000;

    public function pricePerDay(): int
    {
        return max(1, PlatformSetting::int('price_per_day'));
    }

    public function minPaymentAmount(): int
    {
        return max(self::GATEWAY_MINIMUM, PlatformSetting::int('min_payment_amount'));
    }

    /**
     * @param  'amount'|'days'  $mode
     * @return array{original_amount:int, discount_amount:int, amount:int, days:int, discount:?DiscountCode, discount_error:?string}
     */
    public function quote(string $mode, int $value, ?string $rawCode = null): array
    {
        $pricePerDay = $this->pricePerDay();

        if ($mode === 'days') {
            $days     = max(1, $value);
            $original = $days * $pricePerDay;
        } else {
            $original = max(0, $value);
            $days     = intdiv($original, $pricePerDay);
        }

        [$discount, $discountError] = $this->resolveCode($rawCode);

        $discountAmount = $discount ? $discount->discountFor($original) : 0;
        $final          = max(0, $original - $discountAmount);

        return [
            'original_amount' => $original,
            'discount_amount' => $discountAmount,
            'amount'          => $final,
            'days'            => $days,
            'discount'        => $discount,
            'discount_error'  => $discountError,
        ];
    }

    /** خطاهای قابل نمایش برای یک پیش‌فاکتور (خالی یعنی قابل پرداخت است) */
    public function validationErrors(array $quote): array
    {
        $errors = [];

        if ($quote['original_amount'] < $this->minPaymentAmount()) {
            $errors[] = 'حداقل مبلغ قابل پرداخت ' . number_format($this->minPaymentAmount()) . ' تومان است.';
        }

        if ($quote['days'] < 1) {
            $errors[] = 'مبلغ واردشده حتی برای یک روز اشتراک کافی نیست.';
        }

        // مبلغ صفر یعنی تخفیف کل مبلغ را پوشانده؛ این حالت مجاز است و بدون درگاه
        // فعال می‌شود. فقط مبلغ‌های «کم ولی غیرصفر» به درد درگاه نمی‌خورند.
        if ($quote['amount'] > 0 && $quote['amount'] < self::GATEWAY_MINIMUM) {
            $errors[] = 'مبلغ نهایی پس از تخفیف کمتر از حداقل مبلغ قابل پرداخت درگاه است.'
                . ' مبلغ یا تعداد روز را کمی بیشتر کنید.';
        }

        if ($quote['discount_error'] !== null) {
            $errors[] = $quote['discount_error'];
        }

        return $errors;
    }

    /** @return array{0: ?DiscountCode, 1: ?string} */
    private function resolveCode(?string $rawCode): array
    {
        $code = trim((string) $rawCode);

        if ($code === '') {
            return [null, null];
        }

        $discount = DiscountCode::where('code', DiscountCode::normalizeCode($code))->first();

        if (!$discount) {
            return [null, 'کد تخفیف واردشده وجود ندارد.'];
        }

        $reason = $discount->invalidReason();

        return $reason === null ? [$discount, null] : [null, $reason];
    }
}
