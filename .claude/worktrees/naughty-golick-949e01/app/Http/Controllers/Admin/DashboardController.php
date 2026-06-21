<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Representative;
use App\Models\Report;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller {
    public function index() {
        $totalReps      = Representative::count();
        $connectedReps  = Representative::where('is_connected', true)->count();
        $totalReports   = Report::count();
        $totalCategories = Category::count();

        // نمودار گزارش‌ها بر اساس ماه (آخرین ۶ ماه)
        $reportsByMonth = Report::selectRaw('jalali_month, count(*) as total')
            ->groupBy('jalali_month')
            ->orderBy('jalali_month', 'desc')
            ->limit(6)
            ->get()
            ->sortBy('jalali_month')
            ->values();

        // نمودار گزارش‌ها بر اساس دپارتمان
        $reportsByDept = DB::table('reports')
            ->join('departments', 'reports.department_id', '=', 'departments.id')
            ->selectRaw('departments.name as name, count(*) as total')
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('total')
            ->get();

        // نمودار گزارش‌ها بر اساس دسته‌بندی (حداکثر ۸ تا)
        $reportsByCategory = DB::table('reports')
            ->join('categories', 'reports.category_id', '=', 'categories.id')
            ->selectRaw('categories.name as name, count(*) as total')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return view('admin.dashboard', compact(
            'totalReps', 'connectedReps', 'totalReports', 'totalCategories',
            'reportsByMonth', 'reportsByDept', 'reportsByCategory'
        ));
    }
}
