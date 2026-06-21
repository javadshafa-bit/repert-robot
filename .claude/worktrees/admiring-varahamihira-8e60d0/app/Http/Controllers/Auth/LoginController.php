<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller {
    public function showLogin() {
        if (session('admin_logged_in')) return redirect()->route('admin.dashboard');
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

        $user = DB::table('users')->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return back()->withErrors(['email' => 'ایمیل یا رمز عبور اشتباه است.']);
        }

        session(['admin_logged_in' => true, 'admin_name' => $user->name]);
        return redirect()->route('admin.dashboard');
    }

    public function logout() {
        session()->flush();
        return redirect()->route('login');
    }
}