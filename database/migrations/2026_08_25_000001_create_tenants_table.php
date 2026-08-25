<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');                                   // نام سازمان
            $table->string('owner_name');
            $table->string('owner_phone')->nullable();
            $table->string('email');

            // pending_payment | active | expired | suspended
            // عمداً ستون واقعی است و نه مقدار محاسبه‌شده، تا کوئری‌های سوپرادمین و
            // کرون‌ها بتوانند رویش ایندکس بزنند. با Tenant::refreshStatus() هم‌گام می‌شود.
            $table->string('status')->default('pending_payment');

            $table->string('bot_token')->nullable();
            $table->string('bot_username')->nullable();
            $table->string('webhook_secret')->nullable()->unique();
            $table->timestamp('bot_connected_at')->nullable();

            // اشتراک
            $table->timestamp('subscription_ends_at')->nullable();    // null یعنی هنوز اشتراکی نداشته
            $table->boolean('is_unlimited')->default(false);          // تصمیم سوپرادمین: بدون محدودیت زمانی
            $table->timestamp('trial_ends_at')->nullable();           // فعلاً استفاده نمی‌شود

            // تعلیق دستی توسط سوپرادمین
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspended_reason')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('subscription_ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
