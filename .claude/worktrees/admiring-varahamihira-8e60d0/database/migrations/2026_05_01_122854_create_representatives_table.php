<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('representatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone_number')->unique();
            $table->string('chat_id')->nullable()->unique();
            $table->boolean('is_connected')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('representatives'); }
};