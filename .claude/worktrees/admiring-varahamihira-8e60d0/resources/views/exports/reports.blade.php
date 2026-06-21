<table dir="rtl" style="border-collapse: collapse; width: 100%; font-family: Vazirmatn, Tahoma, Arial, sans-serif; font-size: 11px;">
    <thead>
    <tr>
        <th colspan="{{ $totalCols }}" style="border: 1px solid #000; padding: 8px; font-size: 14px; font-weight: bold; text-align: center; vertical-align: middle;">
            گزارش ماه {{ $month }}
        </th>
    </tr>
    </thead>
    <tbody>
    @php
        $byProvince = $reports->groupBy(fn($r) => optional(optional($r->representative)->province)->name ?? 'بدون استان')->sortKeys();
    @endphp

    @foreach($byProvince as $provinceName => $provinceReports)
        {{-- Province row --}}
        <tr>
            <td colspan="{{ $totalCols }}" style="border: 1px solid #000; padding: 6px 8px; font-weight: bold; text-align: center; vertical-align: middle;">
                استان: {{ $provinceName }}
            </td>
        </tr>

        @php
            $byDept = $provinceReports->groupBy(fn($r) => optional($r->department)->name ?? 'بدون دپارتمان')->sortKeys();
        @endphp

        @foreach($byDept as $deptName => $deptReports)
            {{-- Department row --}}
            <tr>
                <td colspan="{{ $totalCols }}" style="border: 1px solid #000; padding: 5px 8px; text-align: center; vertical-align: middle;">
                    دپارتمان: {{ $deptName }}
                </td>
            </tr>

            @php
                $byCategory = $deptReports->groupBy(fn($r) => optional($r->category)->name ?? 'بدون دسته‌بندی');
            @endphp

            @foreach($byCategory as $categoryName => $categoryReports)
                @php $fields = optional($categoryReports->first()->category)->fields ?? collect(); @endphp

                {{-- Category row --}}
                <tr>
                    <td colspan="{{ $totalCols }}" style="border: 1px solid #000; padding: 5px 8px; font-style: italic; text-align: center; vertical-align: middle;">
                        فرم: {{ $categoryName }}
                    </td>
                </tr>

                {{-- Column headers --}}
                <tr>
                    <th style="border: 1px solid #000; padding: 5px 8px; font-weight: bold; text-align: center; vertical-align: middle;">نماینده</th>
                    @foreach($fields as $field)
                        <th style="border: 1px solid #000; padding: 5px 8px; font-weight: bold; text-align: center; vertical-align: middle;">{{ $field->label }}</th>
                    @endforeach
                </tr>

                {{-- Data rows --}}
                @foreach($categoryReports as $report)
                    <tr>
                        <td style="border: 1px solid #000; padding: 4px 8px; text-align: center; vertical-align: middle;">
                            {{ optional($report->representative)->full_name ?? '-' }}
                        </td>
                        @foreach($fields as $field)
                            <td style="border: 1px solid #000; padding: 4px 8px; text-align: center; vertical-align: middle;">
                                @php
                                    $raw    = $report->data[$field->id] ?? null;
                                    $values = is_array($raw) ? array_filter($raw) : ($raw ? [$raw] : []);
                                @endphp
                                @if(count($values))
                                    @foreach($values as $val)
                                        @if($field->type === 'photo' || $field->type === 'document')
                                            {{ $val }}
                                        @elseif($field->type === 'link')
                                            {{ $val }}
                                        @else
                                            {{ $val }}
                                        @endif
                                        @if(!$loop->last) | @endif
                                    @endforeach
                                @else
                                    -
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach

            @endforeach
        @endforeach
    @endforeach
    </tbody>
</table>
