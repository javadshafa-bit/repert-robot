<?php
namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Contracts\View\View;

class ReportsExport implements FromView, WithTitle, WithStyles {
    public function __construct(
        private Collection $reports,
        private string $month
    ) {}

    public function view(): View {
        $maxFields = $this->reports->map(fn($r) => $r->category->fields->count())->max() ?? 0;
        $totalCols = max($maxFields + 1, 3);

        return view('exports.reports', [
            'reports'   => $this->reports,
            'month'     => $this->month,
            'totalCols' => $totalCols,
        ]);
    }

    public function title(): string {
        return "گزارش {$this->month}";
    }

    public function styles(Worksheet $sheet): array {
        $sheet->setRightToLeft(true);

        $lastRow = $sheet->getHighestRow();
        $lastCol = $sheet->getHighestColumn();

        return [
            "A1:{$lastCol}{$lastRow}" => [
                'font' => [
                    'name' => 'Vazirmatn',
                    'size' => 10,
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                    'wrapText'   => true,
                    'readOrder'  => 2, // RTL
                ],
            ],
        ];
    }
}
