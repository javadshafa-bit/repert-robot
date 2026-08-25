<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Payment\PaymentException;
use App\Services\Payment\PaymentProcessor;
use App\Services\Payment\PriceCalculator;
use App\Support\TenantContext;
use Illuminate\Http\Request;

/**
 * اشتراک و پرداخت سازمان.
 * تنها ناحیه‌ای که سازمانِ پرداخت‌نکرده یا منقضی‌شده به آن دسترسی دارد.
 */
class BillingController extends Controller
{
    public function __construct(
        private PriceCalculator $calculator,
        private PaymentProcessor $processor,
    ) {}

    public function index()
    {
        return view('billing.index', [
            'tenant'      => TenantContext::get(),
            'pricePerDay' => $this->calculator->pricePerDay(),
            'minAmount'   => $this->calculator->minPaymentAmount(),
            'payments'    => Payment::latest()->limit(5)->get(),
        ]);
    }

    /** پیش‌فاکتور زنده (AJAX) — کاربر مبلغ و روز نهایی را قبل از رفتن به درگاه می‌بیند */
    public function quote(Request $request)
    {
        $data  = $this->validateInput($request);
        $quote = $this->calculator->quote($data['mode'], (int) $data['value'], $data['discount_code'] ?? null);

        return response()->json([
            'original_amount' => $quote['original_amount'],
            'discount_amount' => $quote['discount_amount'],
            'amount'          => $quote['amount'],
            'days'            => $quote['days'],
            'discount_code'   => $quote['discount']?->code,
            'discount_percent'=> $quote['discount']?->percent,
            'errors'          => $this->calculator->validationErrors($quote),
        ]);
    }

    public function pay(Request $request)
    {
        $data = $this->validateInput($request);

        try {
            $url = $this->processor->start(
                TenantContext::get(),
                $request->user(),
                $data['mode'],
                (int) $data['value'],
                $data['discount_code'] ?? null,
                route('billing.callback'),
            );
        } catch (PaymentException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()->away($url);
    }

    /** بازگشت از درگاه — فعال‌سازی فقط پس از verify موفق سمت سرور */
    public function callback(Request $request)
    {
        $authority = (string) $request->query('Authority', $request->query('authority', ''));
        $status    = (string) $request->query('Status', $request->query('status', 'NOK'));

        if ($authority === '') {
            return redirect()->route('billing.index')->withErrors(['amount' => 'بازگشت نامعتبر از درگاه پرداخت.']);
        }

        try {
            $payment = $this->processor->complete($authority, $status);
        } catch (PaymentException $e) {
            return redirect()->route('billing.index')->withErrors(['amount' => $e->getMessage()]);
        }

        return view('billing.receipt', [
            'tenant'  => TenantContext::get()->fresh(),
            'payment' => $payment,
        ]);
    }

    public function invoices()
    {
        return view('billing.invoices', [
            'tenant'   => TenantContext::get(),
            'payments' => Payment::latest()->paginate(20),
        ]);
    }

    private function validateInput(Request $request): array
    {
        return $request->validate([
            'mode'          => 'required|in:amount,days',
            'value'         => 'required|integer|min:1|max:1000000000',
            'discount_code' => 'nullable|string|max:64',
        ], [
            'mode.required'  => 'نوع خرید مشخص نیست.',
            'value.required' => 'مبلغ یا تعداد روز را وارد کنید.',
            'value.integer'  => 'مقدار واردشده باید عدد باشد.',
            'value.min'      => 'مقدار واردشده باید بزرگ‌تر از صفر باشد.',
        ]);
    }
}
