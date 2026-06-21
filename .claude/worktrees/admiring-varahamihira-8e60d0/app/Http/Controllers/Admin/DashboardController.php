<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Representative;
use App\Models\Report;
use App\Models\Category;
use App\Models\MonthlyStatus;
use Morilog\Jalali\Jalalian;

class DashboardController extends Controller {
    public function index() {
        $totalReps        = Representative::count();
        $connectedReps    = Representative::where('is_connected', true)->count();
        $totalReports     = Report::count();
        $totalCategories  = Category::count();
        $currentMonth     = Jalalian::now()->format('Y-m');
        $closedThisMonth  = MonthlyStatus::where('jalali_month', $currentMonth)->count();

        return view('admin.dashboard', compact(
            'totalReps','connectedReps','totalReports',
            'totalCategories','currentMonth','closedThisMonth'
        ));
    }
}