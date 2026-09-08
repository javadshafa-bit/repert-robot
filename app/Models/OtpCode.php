<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * کد یک‌بارمصرف ورود به ربات.
 *
 * خودِ کد هیچ‌جا ذخیره نمی‌شود — فقط هشش. مقدار خام تنها یک بار، هنگام ساخت،
 * برگردانده می‌شود تا پیامک شود؛ بعد از آن دیگر قابل بازیابی نیست.
 */
class OtpCode extends Model
{
    use BelongsToTenant;

    /** طول کد، عمر آن، و سقف تلاش غلط */
    public const LENGTH       = 6;
    public const TTL_SECONDS  = 120;
    public const MAX_ATTEMPTS = 5;

    /** فاصله‌ی لازم بین دو ارسال، و سقف ارسال در ساعت برای یک شماره */
    public const RESEND_COOLDOWN_SECONDS = 60;
    public const MAX_PER_HOUR            = 5;

    protected $fillable = [
        'representative_id', 'chat_id', 'phone_number',
        'code_hash', 'attempts', 'expires_at', 'consumed_at',
    ];

    protected $casts = [
        'attempts'    => 'integer',
        'expires_at'  => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function representative()
    {
        return $this->belongsTo(Representative::class);
    }

    /**
     * یک کد تازه می‌سازد و مقدار خام را برمی‌گرداند.
     *
     * @return array{0: self, 1: string} [رکورد, کد خام]
     */
    public static function issue(Representative $rep, string $chatId, string $phoneNumber): array
    {
        $plain = str_pad((string) random_int(0, 10 ** self::LENGTH - 1), self::LENGTH, '0', STR_PAD_LEFT);

        $otp = self::create([
            'representative_id' => $rep->id,
            'chat_id'           => $chatId,
            'phone_number'      => $phoneNumber,
            'code_hash'         => Hash::make($plain),
            'attempts'          => 0,
            'expires_at'        => Carbon::now()->addSeconds(self::TTL_SECONDS),
        ]);

        return [$otp, $plain];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null
            && !$this->isExpired()
            && $this->attempts < self::MAX_ATTEMPTS;
    }

    /** تطبیق کد واردشده؛ در هر صورت شمارنده‌ی تلاش را بالا می‌برد */
    public function matches(string $plain): bool
    {
        $this->increment('attempts');

        return Hash::check($plain, $this->code_hash);
    }

    public function consume(): void
    {
        $this->update(['consumed_at' => Carbon::now()]);
    }
}
