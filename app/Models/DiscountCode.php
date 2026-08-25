<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * کد تخفیف — متعلق به پلتفرم است نه سازمان‌ها، پس عمداً BelongsToTenant ندارد.
 */
class DiscountCode extends Model
{
    protected $fillable = [
        'code', 'percent', 'max_uses', 'used_count',
        'starts_at', 'expires_at', 'is_active', 'created_by',
    ];

    protected $casts = [
        'percent'    => 'integer',
        'max_uses'   => 'integer',
        'used_count' => 'integer',
        'starts_at'  => 'datetime',
        'expires_at' => 'datetime',
        'is_active'  => 'boolean',
    ];

    public static function normalizeCode(string $code): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', '', $code)));
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /** دلیل نامعتبر بودن (null یعنی معتبر است) — پیام دقیق، نه «کد نامعتبر» کلی */
    public function invalidReason(): ?string
    {
        if (!$this->is_active) {
            return 'این کد تخفیف غیرفعال شده است.';
        }

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return 'این کد تخفیف هنوز شروع نشده است.';
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return 'مهلت استفاده از این کد تخفیف تمام شده است.';
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return 'ظرفیت استفاده از این کد تخفیف پر شده است.';
        }

        return null;
    }

    public function isUsable(): bool
    {
        return $this->invalidReason() === null;
    }

    /** مبلغ تخفیف روی یک مبلغ (تومان) — همیشه سمت سرور حساب می‌شود */
    public function discountFor(int $amount): int
    {
        return (int) floor($amount * $this->percent / 100);
    }
}
