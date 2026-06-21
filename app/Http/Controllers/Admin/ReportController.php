<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryField;
use App\Models\Department;
use App\Models\Province;
use App\Models\Report;
use App\Models\Representative;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $query = Report::with(['representative.province', 'category', 'department']);

        // دسترسی کاربر
        $allowedDepts = auth()->user()->allowedDepartmentIds();
        if ($allowedDepts !== null) {
            $query->whereIn('department_id', $allowedDepts);
        }

        if ($request->filled('month'))             $query->where('jalali_month', $request->month);
        if ($request->filled('province_id'))       $query->whereHas('representative', fn($q) => $q->where('province_id', $request->province_id));
        if ($request->filled('representative_id')) $query->where('representative_id', $request->representative_id);
        if ($request->filled('department_id'))     $query->where('department_id', $request->department_id);
        if ($request->filled('category_id'))       $query->where('category_id', $request->category_id);

        // فیلتر بر اساس مقدار یک فیلد خاص
        if ($request->filled('filter_field_id') && $request->filled('filter_value')) {
            $filterFieldId = (int) $request->filter_field_id;
            $filterValue   = $request->filter_value;

            $query->where(function ($q) use ($filterFieldId, $filterValue) {
                // جستجو در JSON آرایه
                $q->whereRaw("EXISTS (
                    SELECT 1 FROM json_each(data)
                    WHERE json_extract(json_each.value, '$.field_id') = ?
                      AND json_extract(json_each.value, '$.value') = ?
                )", [$filterFieldId, $filterValue]);
            });
        }

        $reports         = $query->latest()->paginate(20)->withQueryString();
        $provinces       = Province::orderBy('name')->get();
        $departments     = Department::orderBy('name')->get();
        $categories      = Category::orderBy('name')->get();
        $representatives = Representative::orderBy('first_name')->get();
        $months          = Report::select('jalali_month')->distinct()->orderBy('jalali_month', 'desc')->pluck('jalali_month');

        // فیلدهای قابل فیلتر (فقط اگر category انتخاب شده)
        $filterFields  = collect();
        $filterOptions = collect();

        if ($request->filled('category_id')) {
            $filterFields = CategoryField::where('category_id', $request->category_id)
                ->where('type', 'option')
                ->get();

            if ($request->filled('filter_field_id')) {
                $filterField   = CategoryField::with('options')->find($request->filter_field_id);
                $filterOptions = $filterField?->options ?? collect();
            }
        }

        return view('admin.reports.index', compact(
            'reports', 'provinces', 'departments', 'categories', 'representatives', 'months',
            'filterFields', 'filterOptions'
        ));
    }

    public function show(Report $report)
    {
        $report->load(['representative.province', 'department', 'category']);
        return view('admin.reports.show', compact('report'));
    }

    public function destroy(Report $report)
    {
        $report->delete();
        return redirect()->route('admin.reports.index')->with('success', 'گزارش با موفقیت حذف شد.');
    }
}
