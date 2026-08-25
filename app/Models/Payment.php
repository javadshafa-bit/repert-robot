<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * یک تلاش پرداخت. مبالغ به **تومان** ذخیره می‌شوند.
 */
class Payment extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING  = 'pending';
    public const STATUS_PAID     = 'paid';
    public const STATUS_FAILED   = 'failed';
    public const STATUS_CANCELED = 'canceled';

    protected $fillable = [
        'user_id', 'amount', 'original_amount', 'discount_code_id', 'discount_amount',
        'days_granted', 'gateway', 'authority', 'ref_id', 'status', 'paid_at', 'raw_response',
    ];

    protected $casts = [
        'amount'          => 'integer',
        'original_amount' => 'integer',
        'discount_amount' => 'integer',
        'days_granted'    => 'integer',
        'paid_at'         => 'datetime',
        'raw_response'    => 'array',
    ];

    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING  => 'در انتظار پرداخت',
            self::STATUS_PAID     => 'موفق',
            self::STATUS_FAILED   => 'ناموفق',
            self::STATUS_CANCELED => 'لغوشده',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function discountCode()
    {
        return $this->belongsTo(DiscountCode::class);
    }

    /** مبلغ به ریال — زرین‌پال بر حسب ریال کار می‌کند */
    public function amountInRials(): int
    {
        return $this->amount * 10;
    }
}
