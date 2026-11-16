<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * تفاوت‌های بله و تلگرام در یک جا.
 *
 * هر دو API تقریباً یکسان‌اند (sendMessage، inline_keyboard، getFile و…)؛ فرقشان
 * آدرس پایه است و اینکه شناسه‌ی چت‌هایشان ممکن است با هم تداخل کند.
 *
 * «کلید چت» (chat key) شناسه‌ای است که در دیتابیس ذخیره می‌شود
 * (bot_states.chat_id، otp_codes.chat_id، ai_memories.chat_id، representatives.*):
 *   - بله:     همان شناسه‌ی خام (مثل "123456") — تا داده‌های موجود دست نخورند
 *   - تلگرام: با پیشوند "tg:" (مثل "tg:123456")
 * فقط لحظه‌ی صدا زدن API، کلید به شناسه‌ی خام برمی‌گردد.
 */
class Messenger
{
    public const BALE     = 'bale';
    public const TELEGRAM = 'telegram';

    public const ALL = [self::BALE, self::TELEGRAM];

    private const TG_PREFIX = 'tg:';

    public static function label(string $platform): string
    {
        return $platform === self::TELEGRAM ? 'تلگرام' : 'بله';
    }

    public static function apiUrl(string $platform, string $token): string
    {
        return $platform === self::TELEGRAM
            ? "https://api.telegram.org/bot{$token}/"
            : "https://tapi.bale.ai/bot{$token}/";
    }

    public static function fileUrl(string $platform, string $token, string $path): string
    {
        return $platform === self::TELEGRAM
            ? "https://api.telegram.org/file/bot{$token}/{$path}"
            : "https://tapi.bale.ai/file/bot{$token}/{$path}";
    }

    /** لینک عمومی ربات (برای QR و راهنما) */
    public static function botLink(string $platform, string $username): string
    {
        return $platform === self::TELEGRAM
            ? "https://t.me/{$username}"
            : "https://ble.ir/{$username}";
    }

    /** ستونی از representatives که شناسه‌ی چت این پیام‌رسان در آن است */
    public static function repColumn(string $platform): string
    {
        return $platform === self::TELEGRAM ? 'telegram_chat_id' : 'chat_id';
    }

    public static function chatKey(string $platform, int|string $rawChatId): string
    {
        $raw = (string) $rawChatId;

        return $platform === self::TELEGRAM ? self::TG_PREFIX . $raw : $raw;
    }

    public static function platformOfKey(string $chatKey): string
    {
        return str_starts_with($chatKey, self::TG_PREFIX) ? self::TELEGRAM : self::BALE;
    }

    public static function rawChatId(string $chatKey): string
    {
        return str_starts_with($chatKey, self::TG_PREFIX)
            ? substr($chatKey, strlen(self::TG_PREFIX))
            : $chatKey;
    }

    /**
     * کلاینت HTTP برای این پیام‌رسان.
     * تلگرام از داخل ایران فیلتر است؛ اگر TELEGRAM_PROXY تنظیم شده باشد از پشت آن می‌رود.
     */
    public static function http(string $platform, int $timeout = 20): PendingRequest
    {
        $request = Http::timeout($timeout);

        if ($platform === self::TELEGRAM && ($proxy = config('services.telegram.proxy'))) {
            $request = $request->withOptions(['proxy' => $proxy]);
        }

        return $request;
    }
}
