<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * کدهای تخفیف سراسری‌اند: سوپرادمین پلتفرم می‌سازد و برای همه‌ی سازمان‌ها معتبرند،
 * بنابراین tenant_id ندارند.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();                       // حروف بزرگ، بدون فاصله
            $table->unsignedTinyInteger('percent');                 // ۱ تا ۱۰۰
            $table->unsignedInteger('max_uses')->nullable();        // null = نامحدود
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_codes');
    }
};
