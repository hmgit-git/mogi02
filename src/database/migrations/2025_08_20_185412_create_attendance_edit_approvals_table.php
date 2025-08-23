<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendance_edit_approvals', function (Blueprint $table) {
            $table->id();

            // 申請ID（親）
            $table->foreignId('attendance_edit_request_id')
                ->constrained()  // attendance_edit_requests(id)
                ->cascadeOnDelete();

            // 承認者（users.id, admin想定）
            $table->foreignId('approver_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // 承認/却下
            $table->enum('decision', ['approved', 'rejected']);

            // コメント任意
            $table->text('comment')->nullable();

            // 判断日時
            $table->timestampTz('decided_at');

            $table->timestampsTz();

            // 同じ申請に同じ承認者が重複投票できないように
            $table->unique(
                ['attendance_edit_request_id', 'approver_id'],
                'approval_request_approver_unique'
            );

            // 検索用
            $table->index(
                ['attendance_edit_request_id', 'decision'],
                'approval_request_decision_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_edit_approvals');
    }
};
