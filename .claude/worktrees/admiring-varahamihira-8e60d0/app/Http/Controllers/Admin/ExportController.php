<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Models\Report;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ReportsExport;

class ExportController extends Controller {
    public function reports(Request $request) {
        $request->validate([
            'month'       => 'required|string',
            'province_id' => 'nullable|integer|exists:provinces,id',
        ]);

        $month      = $request->month;
        $provinceId = $request->province_id;

        $query = Report::with([
            'representative.province',
            'department',
            'category.fields',
        ])->where('jalali_month', $month);

        if ($provinceId) {
            $query->whereHas('representative', fn($q) => $q->where('province_id', $provinceId));
        }

        $allowedDepts = auth()->user()->allowedDepartmentIds();
        if ($allowedDepts !== null) {
            $query->whereIn('department_id', $allowedDepts);
        }

        $reports = $query->get();

        $provinceName = $provinceId ? Province::find($provinceId)->name : 'همه-استان‌ها';
        $fileName = "گزارش-{$month}-{$provinceName}.xlsx";

        return Excel::download(new ReportsExport($reports, $month), $fileName);
    }
}
