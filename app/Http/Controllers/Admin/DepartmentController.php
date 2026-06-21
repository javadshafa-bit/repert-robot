<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Report;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::withCount('reports')->orderBy('sort_order')->get();
        return view('admin.departments.index', compact('departments'));
    }

    public function create()
    {
        $nextOrder = (Department::max('sort_order') ?? 0) + 1;
        return view('admin.departments.create', compact('nextOrder'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:100',
            'sort_order' => 'integer|min:0',
        ]);

        $sortOrder = $request->filled('sort_order')
            ? (int) $request->sort_order
            : (Department::max('sort_order') ?? 0) + 1;

        Department::create([
            'name'       => $request->name,
            'sort_order' => $sortOrder,
            'is_active'  => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.departments.index')->with('success', 'دپارتمان جدید با موفقیت ایجاد شد.');
    }

    public function show(Department $department)
    {
        $department->loadCount('reports');
        $reports = Report::with(['representative.province', 'category'])
            ->where('department_id', $department->id)
            ->latest()
            ->paginate(20);

        return view('admin.departments.show', compact('department', 'reports'));
    }

    public function edit(Department $department)
    {
        return view('admin.departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department)
    {
        $request->validate([
            'name'       => 'required|string|max:100',
            'sort_order' => 'integer|min:0',
        ]);

        $department->update([
            'name'       => $request->name,
            'sort_order' => $request->sort_order ?? $department->sort_order,
            'is_active'  => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'دپارتمان با موفقیت ویرایش شد.');
    }

    public function destroy(Department $department)
    {
        $department->delete();
        return back()->with('success', 'دپارتمان حذف شد.');
    }
}
