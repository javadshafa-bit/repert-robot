<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('monthly_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('representative_id')->constrained()->cascadeOnDelete();
            $table->string('jalali_month');
            $table->timestamp('closed_at')->nullable();
            $table->unique(['representative_id', 'jalali_month']);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('monthly_statuses'); }
};