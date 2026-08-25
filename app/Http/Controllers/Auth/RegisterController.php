<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

/**
 * ثبت‌نام سازمان جدید.
 *
 * تایید دستی سوپرادمین حذف شده است: ثبت‌نام → ورود خودکار → صفحه‌ی پرداخت.
 * تا وقتی پرداخت انجام نشود، سازمان در وضعیت pending_payment است و فقط
 * صفحه‌ی /billing را می‌بیند (گیت در میدلور ResolveTenant).
 */
class RegisterController extends Controller
{
    public function __construct(private TenantProvisioner $provisioner) {}

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'organization' => 'required|string|max:255',
            'owner_name'   => 'required|string|max:255',
            'owner_phone'  => 'nullable|string|max:20',
            'email'        => 'required|email|max:255|unique:users,email',
            'password'     => ['required', 'confirmed', Password::min(8)],
        ], [
            'organization.required' => 'نام سازمان الزامی است.',
            'owner_name.required'   => 'نام و نام خانوادگی الزامی است.',
            'email.required'        => 'ایمیل الزامی است.',
            'email.email'           => 'فرمت ایمیل صحیح نیست.',
            'email.unique'          => 'این ایمیل قبلاً ثبت شده است.',
            'password.required'     => 'رمز عبور الزامی است.',
            'password.confirmed'    => 'تکرار رمز عبور مطابقت ندارد.',
        ]);

        $user = DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name'           => $data['organization'],
                'owner_name'     => $data['owner_name'],
                'owner_phone'    => $data['owner_phone'] ?? null,
                'email'          => $data['email'],
                'status'         => Tenant::STATUS_PENDING_PAYMENT,
                'webhook_secret' => Tenant::generateWebhookSecret(),
            ]);

            // مالک سازمان: ادمین کامل در محدوده‌ی سازمان خودش، نه پلتفرم
            $user = User::create([
                'tenant_id'         => $tenant->id,
                'name'              => $data['owner_name'],
                'email'             => $data['email'],
                'password'          => $data['password'],
                'is_super_admin'    => true,
                'is_platform_admin' => false,
            ]);

            // سازمان از همین حالا داده‌ی پایه‌اش را دارد (استان‌ها)؛ دیگر
            // منتظر تایید کسی نمی‌ماند.
            $this->provisioner->provision($tenant);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('billing.index')->with(
            'success',
            'سازمان شما ساخته شد. برای فعال شدن پنل و ربات، اشتراک را تهیه کنید.'
        );
    }
}
