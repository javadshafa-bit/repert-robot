<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ربات تلگرام در کنار ربات بله.
 *
 * - هر سازمان می‌تواند هم‌زمان یک ربات بله و یک ربات تلگرام داشته باشد (توکن و وبهوک جدا).
 * - یک نماینده می‌تواند هم در بله و هم در تلگرام وصل باشد؛ ستون chat_id برای بله می‌ماند
 *   و telegram_chat_id برای تلگرام اضافه می‌شود، تا وصل شدن در یکی اتصال دیگری را پاک نکند.
 * - در جدول‌های دیگر (bot_states، otp_codes، ai_memories) شناسه‌ی تلگرام با پیشوند "tg:"
 *   ذخیره می‌شود و نیازی به ستون تازه ندارند. App\Support\Messenger را ببینید.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('telegram_bot_token')->nullable()->after('bot_connected_at');
            $table->string('telegram_bot_username')->nullable()->after('telegram_bot_token');
            $table->timestamp('telegram_connected_at')->nullable()->after('telegram_bot_username');
        });

        Schema::table('representatives', function (Blueprint $table) {
            $table->string('telegram_chat_id')->nullable()->after('chat_id');
            $table->unique(['tenant_id', 'telegram_chat_id']);
        });
    }

    public function down(): void
    {
        Schema::table('representatives', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'telegram_chat_id']);
            $table->dropColumn('telegram_chat_id');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['telegram_bot_token', 'telegram_bot_username', 'telegram_connected_at']);
        });
    }
};
