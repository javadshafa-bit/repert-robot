<?php

namespace App\Models;

use App\Support\Messenger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Tenant extends Model
{
    /** ثبت‌نام کرده ولی هنوز پرداختی نداشته — فقط صفحه‌ی پرداخت را می‌بیند */
    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    /** اشتراک معتبر — پنل و ربات کامل کار می‌کنند */
    public const STATUS_ACTIVE = 'active';

    /** اشتراک تمام شده — فقط صفحه‌ی تمدید */
    public const STATUS_EXPIRED = 'expired';

    /** سوپرادمین دستی معلق کرده — نه پنل، نه ربات */
    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'name', 'owner_name', 'owner_phone', 'email', 'status',
        'bot_token', 'bot_username', 'webhook_secret', 'bot_connected_at',
        'telegram_bot_token', 'telegram_bot_username', 'telegram_connected_at',
        'subscription_ends_at', 'is_unlimited', 'trial_ends_at',
        'suspended_at', 'suspended_reason',
    ];

    protected $casts = [
        'bot_connected_at'     => 'datetime',
        'telegram_connected_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
        'trial_ends_at'        => 'datetime',
        'suspended_at'         => 'datetime',
        'is_unlimited'         => 'boolean',
    ];

    protected $hidden = ['bot_token', 'telegram_bot_token', 'webhook_secret'];

    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING_PAYMENT => 'در انتظار پرداخت',
            self::STATUS_ACTIVE          => 'فعال',
            self::STATUS_EXPIRED         => 'منقضی',
            self::STATUS_SUSPENDED       => 'معلق',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function subscriptionLogs()
    {
        return $this->hasMany(SubscriptionLog::class);
    }

    // ── اشتراک ───────────────────────────────────────────────────────────────

    /**
     * تنها منبع حقیقت درباره‌ی «آیا این سازمان حق استفاده دارد؟».
     * ستون status فقط بازتابِ ذخیره‌شده و ایندکس‌پذیرِ همین است.
     */
    public function hasActiveSubscription(): bool
    {
        if ($this->status === self::STATUS_SUSPENDED) {
            return false;
        }

        if ($this->is_unlimited) {
            return true;
        }

        return $this->subscription_ends_at !== null && $this->subscription_ends_at->isFuture();
    }

    /** وضعیتی که با توجه به اشتراک *باید* داشته باشد */
    public function intendedStatus(): string
    {
        if ($this->status === self::STATUS_SUSPENDED) {
            return self::STATUS_SUSPENDED;
        }

        if ($this->hasActiveSubscription()) {
            return self::STATUS_ACTIVE;
        }

        // هیچ‌وقت اشتراکی نداشته = تازه ثبت‌نام کرده؛ داشته و تمام شده = منقضی
        return $this->subscription_ends_at === null
            ? self::STATUS_PENDING_PAYMENT
            : self::STATUS_EXPIRED;
    }

    /** هم‌گام کردن ستون status با وضعیت واقعی اشتراک */
    public function refreshStatus(): bool
    {
        $intended = $this->intendedStatus();

        if ($intended === $this->status) {
            return false;
        }

        $this->status = $intended;
        $this->save();

        return true;
    }

    /** چند روز تا پایان اشتراک (null یعنی نامحدود یا بدون اشتراک) */
    public function subscriptionDaysLeft(): ?int
    {
        if ($this->is_unlimited || $this->subscription_ends_at === null) {
            return null;
        }

        return max(0, (int) now()->startOfDay()->diffInDays($this->subscription_ends_at->copy()->startOfDay(), false));
    }

    /**
     * افزودن روز به اشتراک.
     *
     * مبنا max(now, subscription_ends_at) است تا تمدیدِ زودهنگام روزهای باقیمانده را نسوزاند.
     * ذخیره‌سازی و ثبت لاگ بیرون از این متد (در SubscriptionService) انجام می‌شود.
     */
    public function extendedEndsAt(int $days): Carbon
    {
        $base = $this->subscription_ends_at !== null && $this->subscription_ends_at->isFuture()
            ? $this->subscription_ends_at->copy()
            : now();

        return $base->addDays($days);
    }

    // ── ربات ─────────────────────────────────────────────────────────────────

    /** رشته‌ی تصادفی و غیرقابل حدس برای مسیر عمومی وبهوک */
    public static function generateWebhookSecret(): string
    {
        do {
            $secret = Str::random(48);
        } while (static::where('webhook_secret', $secret)->exists());

        return $secret;
    }

    /** اگر هنوز webhook_secret ندارد، بساز و ذخیره کن */
    public function ensureWebhookSecret(): string
    {
        if (!$this->webhook_secret) {
            $this->webhook_secret = static::generateWebhookSecret();
            $this->save();
        }

        return $this->webhook_secret;
    }

    public function webhookUrl(): string
    {
        return route('bot.webhook', $this->ensureWebhookSecret());
    }

    /** آدرس وبهوک ربات تلگرام همین سازمان (همان secret، مسیر جدا) */
    public function telegramWebhookUrl(): string
    {
        return route('bot.webhook.telegram', $this->ensureWebhookSecret());
    }

    /**
     * رمزی که تلگرام در هدر X-Telegram-Bot-Api-Secret-Token برمی‌گرداند.
     * از webhook_secret و APP_KEY مشتق می‌شود، پس جایی ذخیره نمی‌شود و با
     * عوض شدن هر کدام، وبهوک قبلی خودبه‌خود بی‌اعتبار می‌شود.
     */
    public function telegramSecretToken(): string
    {
        return hash_hmac('sha256', 'telegram|' . $this->ensureWebhookSecret(), (string) config('app.key'));
    }

    /** توکن ربات این سازمان روی یک پیام‌رسان */
    public function tokenFor(string $platform): ?string
    {
        $token = $platform === Messenger::TELEGRAM ? $this->telegram_bot_token : $this->bot_token;

        return filled($token) ? $token : null;
    }

    public function botApiUrl(string $platform = Messenger::BALE): ?string
    {
        $token = $this->tokenFor($platform);

        return $token ? Messenger::apiUrl($platform, $token) : null;
    }

    /** پیام‌رسان‌هایی که این سازمان برایشان توکن دارد */
    public function botPlatforms(): array
    {
        return array_values(array_filter(Messenger::ALL, fn ($p) => $this->tokenFor($p) !== null));
    }

    public function hasAnyBotToken(): bool
    {
        return $this->botPlatforms() !== [];
    }

    /**
     * ربات این سازمان روی این پیام‌رسان حق کار کردن دارد؟
     * با تمام شدن اشتراک، ربات هم می‌خوابد — نه فقط پنل.
     */
    public function botIsUsable(string $platform = Messenger::BALE): bool
    {
        return $this->hasActiveSubscription() && $this->tokenFor($platform) !== null;
    }

    /** دست‌کم یکی از ربات‌ها قابل استفاده است؟ */
    public function anyBotIsUsable(): bool
    {
        return $this->hasActiveSubscription() && $this->hasAnyBotToken();
    }

    /** محدود کردن کوئری به سازمان‌هایی که دست‌کم یک توکن ربات دارند */
    public function scopeWithAnyBot($query)
    {
        return $query->where(fn ($q) => $q->whereNotNull('bot_token')->orWhereNotNull('telegram_bot_token'));
    }

    /**
     * سازمانی که مسیر سازگاری قدیمیِ وبهوک (`/api/bot/webhook` بدون secret) به آن وصل می‌شود:
     * کم‌ترین idِ فعالِ دارای توکن. فقط تا وقتی روی سرور `tenants:refresh-webhook` اجرا نشده
     * معنا دارد؛ بعد از آن مسیر قدیمی و این متد حذف می‌شوند.
     */
    public static function legacyWebhookOwner(): ?self
    {
        return static::query()
            ->whereNotNull('bot_token')
            ->where('status', self::STATUS_ACTIVE)
            ->orderBy('id')
            ->first();
    }
}
