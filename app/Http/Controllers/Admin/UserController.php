<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * مدل User عمداً global scope مستأجر ندارد (چون سوپرادمین پلتفرم tenant_id = null دارد)،
     * پس فیلتر مستأجر در همین کنترلر دستی اعمال می‌شود.
     */
    private function tenantUsers()
    {
        return User::where('tenant_id', TenantContext::id());
    }

    /** جلوگیری از IDOR: کاربر سازمان دیگر برای این پنل وجود ندارد */
    private function guardTenant(User $user): void
    {
        abort_if($user->tenant_id !== TenantContext::id(), 404);
    }

    public function index()
    {
        $users = $this->tenantUsers()->with('roles')->orderBy('name')->get();
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
            'roles.*'  => [Rule::exists('roles', 'id')->where('tenant_id', TenantContext::id())],
        ]);

        $isSuperAdmin = Auth::user()->isSuperAdmin() && $request->boolean('is_super_admin');

        $user = User::create([
            'tenant_id'      => TenantContext::id(),
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
        $this->guardTenant($user);

        $roles = Role::orderBy('label')->get();
        $user->load('roles');
        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $this->guardTenant($user);

        $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:users,email,' . $user->id,
            'password' => ['nullable', Password::min(8)],
            'roles'    => 'nullable|array',
            'roles.*'  => [Rule::exists('roles', 'id')->where('tenant_id', TenantContext::id())],
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
        $this->guardTenant($user);

        if ($user->id === Auth::id()) {
            return back()->with('error', 'نمی‌توانید حساب خود را حذف کنید.');
        }
        $user->delete();
        return back()->with('success', 'کاربر حذف شد.');
    }
}
