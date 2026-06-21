@php
    use Illuminate\Support\Facades\Storage;

    $byProvince = $reports->groupBy(fn($r) => optional(optional($r->representative)->province)->name ?? 'بدون استان')->sortKeys();
    $maxFields  = $reports->map(fn($r) => $r->category->fields->count())->max() ?? 0;
    $totalCols  = max($maxFields + 1, 3);
@endphp

<table dir="rtl"
       style="border-collapse:collapse;width:100%;font-family:Vazirmatn,Tahoma,Arial,sans-serif;font-size:11px;">
    <thead>
    <tr>
        <th colspan="{{ $totalCols }}"
            style="border:1px solid #000;padding:8px;font-size:14px;font-weight:bold;text-align:center;vertical-align:middle;">
            گزارش ماه {{ $month }}
        </th>
    </tr>
    </thead>
    <tbody>

    @forelse($byProvince as $provinceName => $provReports)

        <tr>
            <td colspan="{{ $totalCols }}"
                style="border:1px solid #000;padding:6px 10px;font-weight:bold;font-size:12px;text-align:center;vertical-align:middle;">
                استان: {{ $provinceName }}
            </td>
        </tr>

        @php
            $byDept = $provReports->groupBy(fn($r) => optional($r->department)->name ?? 'بدون دپارتمان')->sortKeys();
        @endphp

        @foreach($byDept as $deptName => $deptReports)

            <tr>
                <td colspan="{{ $totalCols }}"
                    style="border:1px solid #000;padding:5px 10px;font-size:11px;text-align:center;vertical-align:middle;">
                    دپارتمان: {{ $deptName }}
                </td>
            </tr>

            @php $byCat = $deptReports->groupBy('category_id'); @endphp

            @foreach($byCat as $catId => $catReports)
                @php
                    $category  = $catReports->first()->category;
                    $fields    = $category->fields;
                    $emptyCols = $totalCols - $fields->count() - 1;
                @endphp

                <tr>
                    <td colspan="{{ $totalCols }}"
                        style="border:1px solid #000;padding:5px 10px;font-size:11px;font-style:italic;text-align:center;vertical-align:middle;">
                        فرم: {{ $category->name }}
                    </td>
                </tr>

                <tr>
                    <th style="border:1px solid #000;padding:6px 8px;font-weight:bold;text-align:center;vertical-align:middle;">
                        نام نماینده
                    </th>
                    @foreach($fields as $field)
                        <th style="border:1px solid #000;padding:6px 8px;font-weight:bold;text-align:center;vertical-align:middle;">
                            {{ $field->label }}@if($field->is_multiple)
                                (چندتایی)
                            @endif
                        </th>
                    @endforeach
                    @for($i = 0; $i < $emptyCols; $i++)
                        <th style="border:1px solid #000;"></th>
                    @endfor
                </tr>

                @foreach($catReports as $report)
                    <tr>
                        <td style="border:1px solid #000;padding:5px 8px;text-align:center;vertical-align:middle;">
                            {{ optional($report->representative)->full_name ?? '-' }}
                        </td>
                        @foreach($fields as $field)
                            @php
                                $raw    = $report->data[$field->id] ?? null;
                                $values = is_array($raw) ? array_filter($raw) : ($raw ? [$raw] : []);
                            @endphp
                            <td style="border:1px solid #000;padding:5px 8px;text-align:center;vertical-align:middle;">
                                @if(count($values))
                                    @if($field->type === 'photo' || $field->type === 'document')

                                        @if(count($values) === 1)
                                            <a href="{{ url(Storage::url($values[0])) }}"
                                               style="color:#007aff;text-decoration:none;font-weight:500;">مشاهده
                                                فایل</a>
                                        @else
                                            @foreach($values as $i => $v)
                                                <a href="{{ url(Storage::url($v)) }}"
                                                   style="color:#007aff;text-decoration:none;display:block;font-weight:500;margin-bottom:4px;">فایل {{ $i + 1 }}</a>
                                            @endforeach
                                        @endif

                                    @elseif($field->type === 'link')
                                        @if(count($values) === 1)
                                            <a href="{{ url(Storage::url($values[0])) }}"
                                               style="color:#007aff;text-decoration:none;font-weight:500;">مشاهده
                                                لینک</a>

                                        @else
                                            @foreach($values as $i =>$v)
                                                <a href="{{ url(Storage::url($v)) }}"
                                                   style="color:#007aff;text-decoration:none;font-weight:500;">
                                                    مشاهده
                                                    لینک
                                                    {{$i+1}}
                                                </a>

                                            @endforeach
                                        @endif

                                    @else
                                        {{ implode(' | ', $values) }}
                                    @endif
                                @else
                                    -
                                @endif
                            </td>
                        @endforeach
                        @for($i = 0; $i < $emptyCols; $i++)
                            <td style="border:1px solid #000;"></td>
                        @endfor
                    </tr>
                @endforeach

            @endforeach

        @endforeach

    @empty
        <tr>
            <td colspan="{{ $totalCols }}"
                style="border:1px solid #000;padding:20px;text-align:center;vertical-align:middle;">
                هیچ گزارشی یافت نشد.
            </td>
        </tr>
    @endforelse

    </tbody>
</table>
