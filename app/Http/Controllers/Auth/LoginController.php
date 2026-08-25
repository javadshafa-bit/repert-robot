<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller {
    public function showLogin() {
        if (Auth::check()) return redirect()->to($this->homeFor(Auth::user()));
        return view('auth.login');
    }

    public function login(Request $request) {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ], [
            'email.required'    => 'ایمیل الزامی است.',
            'email.email'       => 'فرمت ایمیل صحیح نیست.',
            'password.required' => 'رمز عبور الزامی است.',
        ]);

        if (!Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'ایمیل یا رمز عبور اشتباه است.']);
        }

        $user = Auth::user();

        // سوپرادمین پلتفرم: بدون مستأجر، مستقیم به پنل پلتفرم
        if ($user->isPlatformAdmin()) {
            $request->session()->regenerate();
            return redirect()->route('platform.dashboard');
        }

        $tenant = $user->tenant;

        // فقط دو حالت جلوی ورود را می‌گیرد: بی‌سازمان بودن، و تعلیقِ دستی.
        // نپرداختن یا انقضای اشتراک جلوی ورود را نمی‌گیرد — کاربر باید به صفحه‌ی
        // پرداخت برسد؛ گیت در میدلور ResolveTenant اعمال می‌شود.
        if (!$tenant || $tenant->status === Tenant::STATUS_SUSPENDED) {
            $message = $tenant?->status === Tenant::STATUS_SUSPENDED
                ? 'حساب سازمان شما توسط پشتیبانی معلق شده است.'
                    . ($tenant->suspended_reason ? ' دلیل: ' . $tenant->suspended_reason : '')
                : 'حساب شما به هیچ سازمانی متصل نیست. با پشتیبانی تماس بگیرید.';

            $this->forceLogout($request);

            return back()->withErrors(['email' => $message]);
        }

        $request->session()->regenerate();

        return $tenant->hasActiveSubscription()
            ? redirect()->intended(route('admin.dashboard'))
            : redirect()->route('billing.index');
    }

    public function logout(Request $request) {
        $this->forceLogout($request);
        return redirect()->route('login');
    }

    private function forceLogout(Request $request): void {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function homeFor($user): string {
        if ($user->isPlatformAdmin()) {
            return route('platform.dashboard');
        }

        return $user->tenant?->hasActiveSubscription()
            ? route('admin.dashboard')
            : route('billing.index');
    }
}
