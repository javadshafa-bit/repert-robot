<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * تاریخچه‌ی تغییر دوره‌ی اشتراک یک سازمان.
 * نوشتن از سمت پنل پلتفرم باید داخل TenantContext::forTenant انجام شود.
 */
class SubscriptionLog extends Model
{
    use BelongsToTenant;

    public const SOURCE_MANUAL  = 'manual';   // تغییر دستی سوپرادمین
    public const SOURCE_PAYMENT = 'payment';  // تمدید خودکار بعد از پرداخت موفق
    public const SOURCE_EXPIRE  = 'expire';   // کرون انقضا

    protected $fillable = [
        'user_id', 'source', 'from_ends_at', 'to_ends_at',
        'from_unlimited', 'to_unlimited', 'from_status', 'to_status', 'note',
    ];

    protected $casts = [
        'from_ends_at'   => 'datetime',
        'to_ends_at'     => 'datetime',
        'from_unlimited' => 'boolean',
        'to_unlimited'   => 'boolean',
    ];

    public static function sourceLabels(): array
    {
        return [
            self::SOURCE_MANUAL  => 'تغییر دستی',
            self::SOURCE_PAYMENT => 'پرداخت',
            self::SOURCE_EXPIRE  => 'انقضا',
        ];
    }

    public function getSourceLabelAttribute(): string
    {
        return self::sourceLabels()[$this->source] ?? $this->source;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
