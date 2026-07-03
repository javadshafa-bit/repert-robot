<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('broadcast_messages', function (Blueprint $table) {
            $table->id();
            $table->string('title');                                   // عنوان داخلی برای لیست ادمین
            $table->text('body');                                      // متن پیام
            $table->string('photo_path')->nullable();                  // عکس اختیاری
            $table->json('province_ids')->nullable();                  // null = همه استان‌ها

            // نوع زمان‌بندی: instant | once | weekly | monthly_jalali
            $table->string('schedule_type')->default('instant');
            $table->dateTime('scheduled_at')->nullable();              // برای once (به وقت تهران)
            $table->string('send_time', 5)->nullable();                // HH:MM برای تکرارشونده
            $table->unsignedTinyInteger('day_of_week')->nullable();    // 0=شنبه ... 6=جمعه
            $table->unsignedTinyInteger('jalali_day')->nullable();     // 1..31 روز ماه شمسی
            $table->date('last_sent_date')->nullable();                // جلوگیری از ارسال دوباره در همان روز

            // وضعیت: pending | sent | active | paused | canceled
            $table->string('status')->default('pending');
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->dateTime('last_sent_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('broadcast_messages'); }
};
