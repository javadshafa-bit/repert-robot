<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount('users')->orderBy('label')->get();
        return view('admin.roles.index', compact('roles'));
    }

    public function create()
    {
        $permissions  = Role::allPermissions();
        $departments  = Department::where('is_active', true)->orderBy('name')->get();
        return view('admin.roles.create', compact('permissions', 'departments'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:60|unique:roles,name|regex:/^[a-z0-9_]+$/',
            'label'       => 'required|string|max:100',
            'permissions' => 'nullable|array',
            'departments' => 'nullable|array',
            'departments.*' => 'exists:departments,id',
        ], [
            'name.regex' => 'نام باید فقط شامل حروف انگلیسی کوچک، اعداد و زیرخط باشد.',
        ]);

        $allDepts = $request->boolean('all_departments', true);

        $role = Role::create([
            'name'            => $request->name,
            'label'           => $request->label,
            'permissions'     => $request->permissions ?? [],
            'all_departments' => $allDepts,
        ]);

        if (!$allDepts) {
            $role->departments()->sync($request->departments ?? []);
        }

        return redirect()->route('admin.roles.index')->with('success', "نقش «{$role->label}» ساخته شد.");
    }

    public function edit(Role $role)
    {
        $role->load('departments');
        $permissions = Role::allPermissions();
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        return view('admin.roles.edit', compact('role', 'permissions', 'departments'));
    }

    public function update(Request $request, Role $role)
    {
        $request->validate([
            'label'         => 'required|string|max:100',
            'permissions'   => 'nullable|array',
            'departments'   => 'nullable|array',
            'departments.*' => 'exists:departments,id',
        ]);

        $allDepts = $request->boolean('all_departments');

        $role->update([
            'label'           => $request->label,
            'permissions'     => $request->permissions ?? [],
            'all_departments' => $allDepts,
        ]);

        $role->departments()->sync($allDepts ? [] : ($request->departments ?? []));

        return redirect()->route('admin.roles.index')->with('success', "نقش «{$role->label}» ویرایش شد.");
    }

    public function destroy(Role $role)
    {
        $role->delete();
        return back()->with('success', "نقش «{$role->label}» حذف شد.");
    }
}
