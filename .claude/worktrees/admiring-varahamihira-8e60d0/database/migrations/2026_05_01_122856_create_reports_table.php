<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('representative_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('jalali_month'); // e.g. "1403-06"
            $table->json('data'); // dynamic field values
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('reports'); }
};