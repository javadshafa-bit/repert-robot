<table class="w-full text-sm text-right">
    <tbody class="divide-y divide-gray-100">
    @forelse($payments as $payment)
        <tr>
            <td class="py-3">{{ number_format($payment->amount) }} تومان</td>
            <td class="py-3 text-gray-500">{{ $payment->days_granted }} روز</td>
            <td class="py-3">
                @php $color = match($payment->status) {
                    \App\Models\Payment::STATUS_PAID   => 'text-green-700',
                    \App\Models\Payment::STATUS_FAILED => 'text-red-700',
                    default                            => 'text-gray-500',
                }; @endphp
                <span class="{{ $color }}">{{ $payment->status_label }}</span>
            </td>
            <td class="py-3 text-gray-500" dir="ltr">{{ $payment->ref_id ?: '—' }}</td>
            <td class="py-3 text-gray-500">
                {{ jdate(($payment->paid_at ?: $payment->created_at)->copy()->setTimezone('Asia/Tehran'))->format('Y/m/d H:i') }}
            </td>
        </tr>
    @empty
        <tr><td class="py-8 text-center text-gray-500">هنوز پرداختی ثبت نشده است.</td></tr>
    @endforelse
    </tbody>
</table>
