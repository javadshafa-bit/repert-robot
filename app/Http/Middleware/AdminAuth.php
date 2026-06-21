<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuth {
    public function handle(Request $request, Closure $next) {
        if (!Auth::check()) {
            return redirect()->route('login');
        }
        // بارگذاری روابط roles و departments یک‌بار برای کل درخواست
        Auth::user()->load('roles.departments');
        return $next($request);
    }
}
