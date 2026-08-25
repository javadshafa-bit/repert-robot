<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * شروع و پایان یک پرداخت.
 *
 * قواعد امنیتی که اینجا تضمین می‌شوند:
 *  ۱. مبلغ فقط از رکورد Payment خوانده می‌شود، نه از درخواست کاربر.
 *  ۲. فعال‌سازی اشتراک فقط بعد از پاسخ موفقِ verify سمت سرور — نه با Status=OK.
 *  ۳. callback با lockForUpdate ایدمپوتنت است؛ باز کردن دوباره‌ی آدرس بازگشت
 *     اشتراک را دو بار اضافه نمی‌کند.
 *  ۴. مصرف کد تخفیف اتمیک و فقط پس از پرداخت موفق است.
 */
class PaymentProcessor
{
    public function __construct(
        private ZarinpalGateway $gateway,
        private PriceCalculator $calculator,
        private SubscriptionService $subscriptions,
    ) {}

    /**
     * ساخت رکورد پرداخت و برگرداندن آدرس درگاه.
     * پیش‌فاکتور دوباره سمت سرور حساب می‌شود؛ چیزی از فرم کاربر مبنا قرار نمی‌گیرد.
     */
    public function start(Tenant $tenant, ?User $user, string $mode, int $value, ?string $code, string $callbackUrl): string
    {
        $quote  = $this->calculator->quote($mode, $value, $code);
        $errors = $this->calculator->validationErrors($quote);

        if ($errors) {
            throw new PaymentException($errors[0]);
        }

        $payment = Payment::create([
            'user_id'          => $user?->id,
            'amount'           => $quote['amount'],
            'original_amount'  => $quote['original_amount'],
            'discount_code_id' => $quote['discount']?->id,
            'discount_amount'  => $quote['discount_amount'],
            'days_granted'     => $quote['days'],
            'gateway'          => 'zarinpal',
            'status'           => Payment::STATUS_PENDING,
        ]);

        $description = "اشتراک {$quote['days']} روزه — {$tenant->name}";

        return $this->gateway->request($payment, $callbackUrl, $description);
    }

    /**
     * بازگشت از درگاه.
     *
     * @param  string  $status  مقدار Status در آدرس بازگشت (OK / NOK) — به‌تنهایی معیار نیست
     */
    public function complete(string $authority, string $status): Payment
    {
        return DB::transaction(function () use ($authority, $status) {
            $payment = Payment::where('authority', $authority)->lockForUpdate()->first();

            if (!$payment) {
                throw new PaymentException('تراکنشی با این شناسه پیدا نشد.');
            }

            // ایدمپوتنت: اگر قبلاً نهایی شده، فقط همان رکورد برگردانده می‌شود
            if ($payment->status !== Payment::STATUS_PENDING) {
                return $payment;
            }

            if (strtoupper($status) !== 'OK') {
                $payment->update([
                    'status'       => Payment::STATUS_CANCELED,
                    'raw_response' => array_merge($payment->raw_response ?? [], ['callback_status' => $status]),
                ]);

                return $payment;
            }

            $result = $this->gateway->verify($payment);

            // مبلغِ برگشتی باید دقیقاً همان چیزی باشد که ذخیره کرده‌ایم
            $amountMismatch = $result['amount'] !== null && $result['amount'] !== $payment->amountInRials();

            if (!$result['ok'] || $amountMismatch) {
                if ($amountMismatch) {
                    Log::warning('zarinpal amount mismatch', [
                        'payment_id' => $payment->id,
                        'expected'   => $payment->amountInRials(),
                        'received'   => $result['amount'],
                    ]);
                }

                $payment->update([
                    'status'       => Payment::STATUS_FAILED,
                    'raw_response' => array_merge($payment->raw_response ?? [], ['verify' => $result['raw']]),
                ]);

                return $payment;
            }

            $payment->update([
                'status'       => Payment::STATUS_PAID,
                'ref_id'       => $result['ref_id'],
                'paid_at'      => now(),
                'raw_response' => array_merge($payment->raw_response ?? [], ['verify' => $result['raw']]),
            ]);

            $this->consumeDiscount($payment);

            $tenant = $payment->tenant()->first();

            $this->subscriptions->grantDays(
                $tenant,
                $payment->days_granted,
                $payment->user_id,
                "افزودن {$payment->days_granted} روز بابت پرداخت " . number_format($payment->amount) . ' تومان'
            );

            return $payment;
        });
    }

    /**
     * مصرف کد تخفیف — فقط بعد از پرداخت موفق و به‌صورت اتمیک.
     * شرط ظرفیت داخل خود UPDATE است تا دو پرداخت هم‌زمان از سقف رد نشوند.
     */
    private function consumeDiscount(Payment $payment): void
    {
        if (!$payment->discount_code_id) {
            return;
        }

        $affected = DB::table('discount_codes')
            ->where('id', $payment->discount_code_id)
            ->where(function ($query) {
                $query->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses');
            })
            ->update([
                'used_count' => DB::raw('used_count + 1'),
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            // پول گرفته شده؛ اشتراک را نگه می‌داریم ولی این را باید دید
            Log::warning('discount capacity exceeded after payment', [
                'payment_id'       => $payment->id,
                'discount_code_id' => $payment->discount_code_id,
            ]);
        }
    }
}
