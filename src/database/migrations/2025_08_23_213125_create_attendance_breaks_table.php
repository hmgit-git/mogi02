<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendance_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')
                ->constrained('attendances')
                ->cascadeOnDelete();

            // 休憩は何回でも：開始〜終了のペアを複数持てる
            $table->timestamp('start_at');
            $table->timestamp('end_at')->nullable();

            $table->timestamps();

            // よく使う検索
            $table->index(['attendance_id', 'start_at']);
            $table->index(['attendance_id', 'end_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_breaks');
    }
};
