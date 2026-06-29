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

        // فیلترهای چندگانه بر اساس فیلدهای گزارش (MySQL JSON_TABLE)
        foreach ((array) $request->input('ff', []) as $filter) {
            if (empty($filter['fid']) || !isset($filter['val']) || $filter['val'] === '') continue;
            $fid = (int) $filter['fid'];
            $val = $filter['val'];
            $op  = $filter['op'] ?? 'contains';

            $query->where(function ($q) use ($fid, $val, $op) {
                if ($op === 'exact') {
                    // فیلد گزینه‌ای — تطابق دقیق
                    $q->whereRaw("EXISTS (
                        SELECT 1 FROM JSON_TABLE(data, '$[*]' COLUMNS(
                            fid INT PATH '$.field_id',
                            val VARCHAR(500) PATH '$.value'
                        )) AS jt
                        WHERE jt.fid = ? AND jt.val = ?
                    )", [$fid, $val]);
                } elseif ($op === 'has_photo') {
                    // فیلد عکس — وجود مقدار
                    $q->whereRaw("EXISTS (
                        SELECT 1 FROM JSON_TABLE(data, '$[*]' COLUMNS(
                            fid INT PATH '$.field_id',
                            val VARCHAR(500) PATH '$.value'
                        )) AS jt
                        WHERE jt.fid = ? AND jt.val IS NOT NULL AND jt.val != ''
                    )", [$fid]);
                } else {
                    // فیلد متنی/لینک — جستجوی حاوی
                    $q->whereRaw("EXISTS (
                        SELECT 1 FROM JSON_TABLE(data, '$[*]' COLUMNS(
                            fid INT PATH '$.field_id',
                            val VARCHAR(1000) PATH '$.value'
                        )) AS jt
                        WHERE jt.fid = ? AND jt.val LIKE ?
                    )", [$fid, '%' . $val . '%']);
                }
            });
        }

        $reports         = $query->latest()->paginate(20)->withQueryString();
        $provinces       = Province::orderBy('name')->get();
        $departments     = Department::orderBy('name')->get();
        $categories      = Category::orderBy('name')->get();
        $representatives = Representative::orderBy('first_name')->get();
        $months          = Report::select('jalali_month')->distinct()->orderBy('jalali_month', 'desc')->pluck('jalali_month');

        // همه فیلدهای دسته‌بندی انتخابی (برای فیلتر پویا)
        $filterFields = collect();
        $fieldsJson   = '[]';
        if ($request->filled('category_id')) {
            $filterFields = CategoryField::where('category_id', $request->category_id)
                ->with('options:id,field_id,label,sort_order')
                ->orderBy('sort_order')
                ->get();

            // محاسبه عمق هر فیلد در درخت برای نمایش indent
            $depthCache = [];
            $getDepth   = function ($field) use (&$getDepth, $filterFields, &$depthCache) {
                if (isset($depthCache[$field->id])) return $depthCache[$field->id];
                if (!$field->parent_field_id && !$field->parent_option_id) {
                    return $depthCache[$field->id] = 0;
                }
                if ($field->parent_field_id) {
                    $parent = $filterFields->firstWhere('id', $field->parent_field_id);
                    return $depthCache[$field->id] = ($parent ? $getDepth($parent) + 1 : 1);
                }
                $parentField = $filterFields->first(fn($f) => $f->options->contains('id', $field->parent_option_id));
                return $depthCache[$field->id] = ($parentField ? $getDepth($parentField) + 1 : 1);
            };

            $fieldsJson = $filterFields->map(fn($f) => [
                'id'      => $f->id,
                'label'   => $f->label,
                'type'    => $f->type,
                'depth'   => $getDepth($f),
                'options' => $f->options->map(fn($o) => ['label' => $o->label])->values(),
            ])->toJson(JSON_UNESCAPED_UNICODE);
        }

        return view('admin.reports.index', compact(
            'reports', 'provinces', 'departments', 'categories', 'representatives', 'months',
            'filterFields', 'fieldsJson'
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
