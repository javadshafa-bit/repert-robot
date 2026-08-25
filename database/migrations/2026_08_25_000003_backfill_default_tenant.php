<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * انتقال داده‌ی موجود (تک‌سازمانی) به یک مستأجر پیش‌فرض.
 *
 * بدون این migration، بعد از فعال شدن global scope همه‌ی رکوردهای قدیمی
 * (که tenant_id = null دارند) از دید اپ ناپدید می‌شوند.
 */
return new class extends Migration {
    private array $tables = [
        'users', 'roles', 'provinces', 'representatives', 'departments',
        'department_fields', 'categories', 'category_fields', 'field_options',
        'reports', 'bot_states', 'monthly_statuses', 'broadcast_messages', 'settings',
    ];

    public function up(): void
    {
        // اگر هیچ داده‌ای وجود ندارد (نصب تازه)، مستأجر پیش‌فرض لازم نیست
        if (!$this->hasLegacyData()) {
            return;
        }

        // اگر قبلاً اجرا شده، دوباره مستأجر نساز
        $existingId = DB::table('tenants')->min('id');

        if ($existingId === null) {
            $botToken = Schema::hasTable('settings')
                ? DB::table('settings')->where('key', 'bot_token')->value('value')
                : null;

            $owner = DB::table('users')->orderBy('id')->first();

            // سازمان اصلی تابع اشتراک است (معاف نیست)، ولی نباید با اولین deploy
            // رباتش بخوابد؛ پس یک دوره‌ی یک‌ساله می‌گیرد. سوپرادمین می‌تواند از پنل
            // پلتفرم هر لحظه کوتاه/بلندش کند یا نامحدودش کند.
            $existingId = DB::table('tenants')->insertGetId([
                'name'                 => 'حوزه هنری (سازمان اصلی)',
                'owner_name'           => $owner->name  ?? 'مدیر سیستم',
                'owner_phone'          => null,
                'email'                => $owner->email ?? 'admin@example.com',
                'status'               => 'active',
                'bot_token'            => $botToken,
                'bot_username'         => null,
                'webhook_secret'       => Str::random(48),
                'bot_connected_at'     => $botToken ? now() : null,
                'subscription_ends_at' => now()->addYear(),
                'is_unlimited'         => false,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        }

        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => $existingId]);
            }
        }
    }

    public function down(): void
    {
        // روی نصبی با چند مستأجر، این rollback مالکیت همه‌ی رکوردها را پاک می‌کند و
        // راه برگشتی ندارد (نمی‌دانیم کدام رکورد مال کدام سازمان بود). پس اجازه نمی‌دهیم.
        if (DB::table('tenants')->count() > 1) {
            throw new \RuntimeException(
                'rollback این migration با بیش از یک مستأجر داده را نابود می‌کند. اگر واقعاً می‌خواهی، دستی انجام بده.'
            );
        }

        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                DB::table($table)->update(['tenant_id' => null]);
            }
        }
    }

    private function hasLegacyData(): bool
    {
        foreach (['representatives', 'reports', 'categories', 'departments', 'users'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                return true;
            }
        }

        return false;
    }
};
