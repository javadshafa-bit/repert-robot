<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Models\Representative;
use App\Models\Report;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller {
    public function index(Request $request) {
        $provinceId = $request->filled('province_id') ? (int) $request->province_id : null;
        $provinces  = Province::orderBy('name')->get();

        $totalReps       = Representative::count();
        $connectedReps   = Representative::where('is_connected', true)->count();
        $totalReports    = Report::count();
        $totalCategories = Category::count();

        // نمودار گزارش‌ها بر اساس ماه (فیلتر استان اعمال می‌شود)
        $reportsByMonth = Report::when($provinceId, fn($q) =>
                $q->whereHas('representative', fn($r) => $r->where('province_id', $provinceId))
            )
            ->selectRaw('jalali_month, count(*) as total')
            ->groupBy('jalali_month')
            ->orderBy('jalali_month', 'desc')
            ->limit(6)
            ->get()
            ->sortBy('jalali_month')
            ->values();

        // نمودار گزارش‌ها بر اساس دپارتمان (فیلتر استان اعمال می‌شود)
        $reportsByDept = DB::table('reports')
            ->join('departments', 'reports.department_id', '=', 'departments.id')
            ->join('representatives as reps', 'reports.representative_id', '=', 'reps.id')
            ->when($provinceId, fn($q) => $q->where('reps.province_id', $provinceId))
            ->selectRaw('departments.name as name, count(*) as total')
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('total')
            ->get();

        // نمودار گزارش‌ها بر اساس دسته‌بندی (فیلتر استان اعمال می‌شود)
        $reportsByCategory = DB::table('reports')
            ->join('categories', 'reports.category_id', '=', 'categories.id')
            ->join('representatives as reps', 'reports.representative_id', '=', 'reps.id')
            ->when($provinceId, fn($q) => $q->where('reps.province_id', $provinceId))
            ->selectRaw('categories.name as name, count(*) as total')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        // توزیع گزارش‌ها بر اساس استان (همیشه کلی، بدون فیلتر)
        $reportsByProvince = DB::table('reports')
            ->join('representatives', 'reports.representative_id', '=', 'representatives.id')
            ->join('provinces', 'representatives.province_id', '=', 'provinces.id')
            ->selectRaw('provinces.name as name, count(*) as total')
            ->groupBy('provinces.id', 'provinces.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact(
            'totalReps', 'connectedReps', 'totalReports', 'totalCategories',
            'reportsByMonth', 'reportsByDept', 'reportsByCategory', 'reportsByProvince',
            'provinces', 'provinceId'
        ));
    }
}
