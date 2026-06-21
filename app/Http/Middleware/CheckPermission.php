<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPermission {
    public function handle(Request $request, Closure $next, string $permission) {
        if (!Auth::user()->hasPermission($permission)) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'شما دسترسی لازم برای این بخش را ندارید.');
        }
        return $next($request);
    }
}
