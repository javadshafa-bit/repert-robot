<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // null = پایگاه دانش مشترک سازمان؛ مقدار = حافظه‌ی همان کاربر
            $table->string('chat_id', 32)->nullable();

            // message: پیام‌های گفتگو | knowledge: تکه‌های پایگاه دانش
            $table->string('kind', 16);
            $table->string('role', 16)->nullable();      // user | assistant (فقط برای message)
            $table->string('source')->nullable();         // نام فایل/عنوان دانش

            $table->text('content');
            // بردار embedding به‌صورت float32 فشرده (pack 'g*') — حدود ۶KB برای ۱۵۳۶ بُعد
            $table->binary('embedding')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'chat_id', 'kind', 'id']);
            $table->index(['tenant_id', 'kind']);
        });

        // binary در MySQL پیش‌فرض BLOB (۶۴KB) است که کافی است؛ برای اطمینان MEDIUMBLOB می‌کنیم.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE ai_memories MODIFY embedding MEDIUMBLOB NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_memories');
    }
};
