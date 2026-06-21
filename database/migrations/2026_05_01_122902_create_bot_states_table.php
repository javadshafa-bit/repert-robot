<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('bot_states', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id')->unique();
            $table->string('step')->default('idle');
            $table->foreignId('representative_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('jalali_month')->nullable();
            $table->integer('current_field_index')->default(0);
            $table->json('draft_data')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('bot_states'); }
};