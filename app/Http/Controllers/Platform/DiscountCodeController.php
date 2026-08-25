<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use App\Support\JalaliDate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * مدیریت کدهای تخفیف — کدها سراسری‌اند و سوپرادمین پلتفرم مالکشان است.
 */
class DiscountCodeController extends Controller
{
    public function index()
    {
        $codes = DiscountCode::orderByDesc('created_at')->paginate(20);

        return view('platform.discount-codes.index', compact('codes'));
    }

    public function create()
    {
        return view('platform.discount-codes.form', ['code' => new DiscountCode(['percent' => 20, 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        DiscountCode::create($data + ['created_by' => $request->user()->id, 'used_count' => 0]);

        return redirect()->route('platform.discount-codes.index')->with('success', 'کد تخفیف ساخته شد.');
    }

    public function edit(DiscountCode $discountCode)
    {
        return view('platform.discount-codes.form', ['code' => $discountCode]);
    }

    public function update(Request $request, DiscountCode $discountCode)
    {
        $data = $this->validated($request, $discountCode);

        if ($data['max_uses'] !== null && $data['max_uses'] < $discountCode->used_count) {
            return back()->withInput()->withErrors([
                'max_uses' => "این کد تا حالا {$discountCode->used_count} بار استفاده شده؛ سقف نمی‌تواند کمتر از آن باشد.",
            ]);
        }

        $discountCode->update($data);

        return redirect()->route('platform.discount-codes.index')->with('success', 'کد تخفیف به‌روزرسانی شد.');
    }

    public function toggle(DiscountCode $discountCode)
    {
        $discountCode->update(['is_active' => !$discountCode->is_active]);

        return back()->with('success', $discountCode->is_active ? 'کد فعال شد.' : 'کد غیرفعال شد.');
    }

    public function destroy(DiscountCode $discountCode)
    {
        // کدی که استفاده شده تاریخچه‌ی پرداخت‌ها را نگه می‌دارد؛ فقط غیرفعالش کن
        if ($discountCode->used_count > 0) {
            return back()->with('error', 'این کد قبلاً استفاده شده و قابل حذف نیست؛ می‌توانید غیرفعالش کنید.');
        }

        $discountCode->delete();

        return redirect()->route('platform.discount-codes.index')->with('success', 'کد تخفیف حذف شد.');
    }

    private function validated(Request $request, ?DiscountCode $existing = null): array
    {
        $request->merge(['code' => DiscountCode::normalizeCode((string) $request->input('code'))]);

        $data = $request->validate([
            'code'       => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9_-]+$/',
                             Rule::unique('discount_codes', 'code')->ignore($existing?->id)],
            'percent'    => 'required|integer|min:1|max:100',
            'max_uses'   => 'nullable|integer|min:1',
            'starts_at'  => 'nullable|string|max:20',
            'expires_at' => 'nullable|string|max:20',
            'is_active'  => 'nullable|boolean',
        ], [
            'code.required'  => 'کد الزامی است.',
            'code.regex'     => 'کد فقط می‌تواند شامل حروف انگلیسی بزرگ، عدد، خط تیره و زیرخط باشد.',
            'code.unique'    => 'کدی با همین عنوان از قبل وجود دارد.',
            'percent.min'    => 'درصد تخفیف باید بین ۱ تا ۱۰۰ باشد.',
            'percent.max'    => 'درصد تخفیف باید بین ۱ تا ۱۰۰ باشد.',
            'max_uses.min'   => 'سقف استفاده حداقل ۱ است.',
        ]);

        try {
            $startsAt = JalaliDate::parse($data['starts_at'] ?? null);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['starts_at' => $e->getMessage()]);
        }

        try {
            $expiresAt = JalaliDate::parse($data['expires_at'] ?? null, endOfDay: true);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['expires_at' => $e->getMessage()]);
        }

        if ($startsAt && $expiresAt && $expiresAt->lessThan($startsAt)) {
            throw ValidationException::withMessages(['expires_at' => 'تاریخ پایان نمی‌تواند قبل از تاریخ شروع باشد.']);
        }

        return [
            'code'       => $data['code'],
            'percent'    => (int) $data['percent'],
            'max_uses'   => $data['max_uses'] !== null ? (int) $data['max_uses'] : null,
            'starts_at'  => $startsAt,
            'expires_at' => $expiresAt,
            'is_active'  => $request->boolean('is_active'),
        ];
    }
}
