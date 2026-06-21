<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('roles')->orderBy('name')->get();
        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $roles = Role::orderBy('label')->get();
        return view('admin.users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:users,email',
            'password' => ['required', Password::min(8)],
            'roles'    => 'nullable|array',
            'roles.*'  => 'exists:roles,id',
        ]);

        $isSuperAdmin = Auth::user()->isSuperAdmin() && $request->boolean('is_super_admin');

        $user = User::create([
            'name'           => $request->name,
            'email'          => $request->email,
            'password'       => Hash::make($request->password),
            'is_super_admin' => $isSuperAdmin,
        ]);

        $user->roles()->sync($request->roles ?? []);

        return redirect()->route('admin.users.index')->with('success', "کاربر «{$user->name}» ساخته شد.");
    }

    public function edit(User $user)
    {
        $roles = Role::orderBy('label')->get();
        $user->load('roles');
        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:users,email,' . $user->id,
            'password' => ['nullable', Password::min(8)],
            'roles'    => 'nullable|array',
            'roles.*'  => 'exists:roles,id',
        ]);

        $data = [
            'name'  => $request->name,
            'email' => $request->email,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        // فقط سوپر ادمین می‌تواند سوپر ادمین دیگری بسازد
        if (Auth::user()->isSuperAdmin()) {
            $data['is_super_admin'] = $request->boolean('is_super_admin');
        }

        $user->update($data);
        $user->roles()->sync($request->roles ?? []);

        return redirect()->route('admin.users.index')->with('success', "کاربر «{$user->name}» ویرایش شد.");
    }

    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'نمی‌توانید حساب خود را حذف کنید.');
        }
        $user->delete();
        return back()->with('success', 'کاربر حذف شد.');
    }
}
