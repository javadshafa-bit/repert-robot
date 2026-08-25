<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تاریخچه‌ی هر تغییر دوره‌ی اشتراک: چه کسی، کِی، از چه تاریخی به چه تاریخی، چرا.
 * هم تغییرهای دستی سوپرادمین و هم تمدیدهای خودکارِ پرداخت اینجا ثبت می‌شوند.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();        // null = سیستم (کرون)
            $table->string('source');                                 // manual|payment|expire
            $table->timestamp('from_ends_at')->nullable();
            $table->timestamp('to_ends_at')->nullable();
            $table->boolean('from_unlimited')->default(false);
            $table->boolean('to_unlimited')->default(false);
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
        });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('subscription_logs', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_logs');
    }
};
