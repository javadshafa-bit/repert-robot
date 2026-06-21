<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ReportsExport implements FromArray, WithHeadings, WithTitle, WithStyles
{
    private array $rows;
    private array $headings;

    public function __construct(
        private Collection $reports,
        private string $title = 'گزارش‌ها'
    ) {
        $this->build();
    }

    private function build(): void
    {
        // جمع‌آوری همه label های منحصربه‌فرد در ترتیب ظهور
        $allLabels = [];
        foreach ($this->reports as $report) {
            $data = is_array($report->data) ? $report->data : [];
            foreach ($data as $item) {
                $label = $item['label'] ?? '';
                if ($label && !in_array($label, $allLabels)) {
                    $allLabels[] = $label;
                }
            }
        }

        // هدرهای ثابت + هدرهای داینامیک
        $this->headings = array_merge(
            ['ردیف', 'نماینده', 'استان', 'دپارتمان', 'دسته‌بندی', 'ماه', 'تاریخ ثبت'],
            $allLabels
        );

        // ساختن سطرها
        $this->rows = [];
        $rowNum = 1;
        foreach ($this->reports as $report) {
            $data = is_array($report->data) ? $report->data : [];

            // index by label
            $byLabel = [];
            foreach ($data as $item) {
                $label = $item['label'] ?? '';
                $val   = $item['value'] ?? '';
                if (is_array($val)) {
                    $val = implode('، ', array_map(fn($v) => is_string($v) && str_starts_with($v, 'uploads/') ? '[فایل]' : $v, $val));
                } elseif (is_string($val) && str_starts_with($val, 'uploads/')) {
                    $val = '[فایل]';
                }
                $byLabel[$label] = $val;
            }

            $row = [
                $rowNum++,
                ($report->representative->first_name ?? '') . ' ' . ($report->representative->last_name ?? ''),
                $report->representative->province->name ?? '',
                $report->department->name ?? '',
                $report->category->name ?? '',
                $report->jalali_month ?? '',
                $report->created_at?->format('Y-m-d') ?? '',
            ];

            foreach ($allLabels as $label) {
                $row[] = $byLabel[$label] ?? '';
            }

            $this->rows[] = $row;
        }
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->setRightToLeft(true);
        $lastRow = max($sheet->getHighestRow(), 1);
        $lastCol = $sheet->getHighestColumn();

        return [
            "A1:{$lastCol}1" => [
                'font'      => ['bold' => true, 'name' => 'Vazirmatn', 'size' => 10],
                'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => 'EEF2FF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
            "A1:{$lastCol}{$lastRow}" => [
                'font'      => ['name' => 'Vazirmatn', 'size' => 10],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true, 'readOrder' => 2],
            ],
        ];
    }
}
