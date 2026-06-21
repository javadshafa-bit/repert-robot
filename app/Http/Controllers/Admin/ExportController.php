<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryField;
use App\Models\Province;
use App\Models\Report;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ReportsExport;

class ExportController extends Controller
{
    public function reports(Request $request)
    {
        $allowedDepts = auth()->user()->allowedDepartmentIds();

        $query = Report::with(['representative.province', 'department', 'category'])
            ->when($allowedDepts !== null, fn($q) => $q->whereIn('department_id', $allowedDepts));

        if ($request->filled('month'))             $query->where('jalali_month', $request->month);
        if ($request->filled('province_id'))       $query->whereHas('representative', fn($q) => $q->where('province_id', $request->province_id));
        if ($request->filled('representative_id')) $query->where('representative_id', $request->representative_id);
        if ($request->filled('department_id'))     $query->where('department_id', $request->department_id);
        if ($request->filled('category_id'))       $query->where('category_id', $request->category_id);

        // فیلتر فیلد خاص
        if ($request->filled('filter_field_id') && $request->filled('filter_value')) {
            $filterFieldId = (int) $request->filter_field_id;
            $filterValue   = $request->filter_value;
            $query->whereRaw("EXISTS (
                SELECT 1 FROM json_each(data)
                WHERE json_extract(json_each.value, '$.field_id') = ?
                  AND json_extract(json_each.value, '$.value') = ?
            )", [$filterFieldId, $filterValue]);
        }

        $reports = $query->orderBy('representative_id')->get();

        // عنوان فایل
        $parts = [];
        if ($request->filled('month'))        $parts[] = $request->month;
        if ($request->filled('province_id'))  $parts[] = Province::find($request->province_id)?->name ?? '';
        if ($request->filled('category_id'))  $parts[] = Category::find($request->category_id)?->name ?? '';
        if ($request->filled('filter_value')) $parts[] = $request->filter_value;

        $titleParts = array_filter($parts);
        $title      = 'گزارش ' . implode('-', $titleParts);
        $fileName   = 'گزارش-' . implode('-', $titleParts) . '.xlsx';

        return Excel::download(new ReportsExport($reports, $title), $fileName);
    }
}
