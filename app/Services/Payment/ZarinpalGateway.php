<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * اتصال به درگاه زرین‌پال (API v4).
 *
 * ⚠️ آدرس‌ها و نام فیلدها بر اساس API v4 نوشته شده‌اند ولی زرین‌پال آن‌ها را عوض می‌کند؛
 * قبل از رفتن روی merchant_id واقعی، با مستندات روز تطبیق داده شود.
 *
 * قواعد ثابت این کلاس:
 *  - مبلغ همیشه از رکورد Payment خوانده می‌شود، هرگز از درخواست کاربر.
 *  - مبالغ در دیتابیس به تومان‌اند؛ به درگاه ریال فرستاده می‌شود (currency = IRR).
 *  - merchant_id هیچ‌وقت در raw_response لاگ نمی‌شود.
 */
class ZarinpalGateway
{
    public const LIVE_BASE    = 'https://payment.zarinpal.com';
    public const SANDBOX_BASE = 'https://sandbox.zarinpal.com';

    public function isSandbox(): bool
    {
        return PlatformSetting::bool('zarinpal_sandbox');
    }

    public function baseUrl(): string
    {
        return $this->isSandbox() ? self::SANDBOX_BASE : self::LIVE_BASE;
    }

    public function merchantId(): string
    {
        return (string) PlatformSetting::get('zarinpal_merchant_id');
    }

    public function startPayUrl(string $authority): string
    {
        return $this->baseUrl() . '/pg/StartPay/' . $authority;
    }

    /**
     * ساخت تراکنش روی درگاه و برگرداندن آدرس هدایت کاربر.
     * authority روی همین رکورد Payment ذخیره می‌شود.
     */
    public function request(Payment $payment, string $callbackUrl, string $description): string
    {
        if ($this->merchantId() === '') {
            throw new PaymentException('شناسه پذیرنده‌ی درگاه پرداخت هنوز تنظیم نشده است. با پشتیبانی تماس بگیرید.');
        }

        $response = $this->post('/pg/v4/payment/request.json', [
            'merchant_id'  => $this->merchantId(),
            'amount'       => $payment->amountInRials(),
            'currency'     => 'IRR',
            'callback_url' => $callbackUrl,
            'description'  => $description,
            'metadata'     => array_filter([
                'email'  => $payment->tenant?->email,
                'mobile' => $payment->tenant?->owner_phone,
            ]),
        ]);

        $code      = (int) data_get($response, 'data.code');
        $authority = (string) data_get($response, 'data.authority', '');

        if ($code !== 100 || $authority === '') {
            Log::warning('zarinpal request failed', [
                'payment_id' => $payment->id,
                'code'       => $code,
                'errors'     => data_get($response, 'errors'),
            ]);

            throw new PaymentException($this->errorMessage($response, 'ساخت تراکنش روی درگاه ناموفق بود.'));
        }

        $payment->forceFill([
            'authority'    => $authority,
            'raw_response' => ['request' => $response],
        ])->save();

        return $this->startPayUrl($authority);
    }

    /**
     * تایید سمت سرور.
     *
     * مبلغ ارسالی همان مبلغ ذخیره‌شده روی Payment است؛ اگر درگاه مبلغ دیگری گزارش کند،
     * پرداخت ناموفق تلقی می‌شود (جلوگیری از دستکاری مبلغ).
     *
     * @return array{ok: bool, code: int, ref_id: ?string, amount: ?int, raw: array}
     */
    public function verify(Payment $payment): array
    {
        $response = $this->post('/pg/v4/payment/verify.json', [
            'merchant_id' => $this->merchantId(),
            'amount'      => $payment->amountInRials(),
            'authority'   => $payment->authority,
        ]);

        $code = (int) data_get($response, 'data.code');

        return [
            // ۱۰۰ = تایید شد، ۱۰۱ = قبلاً تایید شده بود (هر دو یعنی پول پرداخت شده)
            'ok'     => in_array($code, [100, 101], true),
            'code'   => $code,
            'ref_id' => data_get($response, 'data.ref_id') !== null
                ? (string) data_get($response, 'data.ref_id')
                : null,
            'amount' => data_get($response, 'data.amount') !== null
                ? (int) data_get($response, 'data.amount')
                : null,
            'raw'    => $response,
        ];
    }

    /** درخواست خام + پاکسازی merchant_id از هر چیزی که ذخیره می‌شود */
    private function post(string $path, array $payload): array
    {
        try {
            $response = Http::asJson()
                ->acceptJson()
                ->timeout(25)
                ->post($this->baseUrl() . $path, $payload);
        } catch (\Throwable $e) {
            Log::warning('zarinpal connection failed: ' . $e->getMessage());

            throw new PaymentException('ارتباط با درگاه پرداخت برقرار نشد. کمی بعد دوباره تلاش کنید.');
        }

        $body = $response->json();

        if (!is_array($body)) {
            throw new PaymentException('پاسخ نامعتبر از درگاه پرداخت دریافت شد.');
        }

        return $this->scrub($body);
    }

    /** merchant_id نباید در دیتابیس یا لاگ بنشیند */
    private function scrub(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($key === 'merchant_id') {
                $data[$key] = '[hidden]';
            } elseif (is_array($value)) {
                $data[$key] = $this->scrub($value);
            }
        }

        return $data;
    }

    private function errorMessage(array $response, string $fallback): string
    {
        $message = data_get($response, 'errors.message')
            ?? data_get($response, 'errors.0.message')
            ?? data_get($response, 'data.message');

        return is_string($message) && $message !== '' ? $message : $fallback;
    }
}
