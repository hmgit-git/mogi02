<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceEditRequest extends Model
{
    // 一括代入
    protected $fillable = [
        'attendance_id',
        'applicant_id',
        'requested_changes',
        'reason',
        'status',          // pending / approved / rejected
        'decided_at',      // 任意: 最終決定時刻を使うなら
        'decided_by',      // 任意: 決定した管理者の users.id
        'supersedes_request_id', // 任意: 再申請の親
    ];

    // キャスト
    protected $casts = [
        'requested_changes' => 'array',
        'decided_at'        => 'datetime',
    ];

    // デフォルト値（DBのdefaultと二重化でもOKな保険）
    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    // 対象の勤怠レコード
    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    // 申請者（User）
    public function applicant()
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    // 承認履歴（1対多）
    public function approvals()
    {
        return $this->hasMany(AttendanceEditApproval::class);
    }

    // 決定者（任意）
    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    // 再申請の親（任意）
    public function parentRequest()
    {
        return $this->belongsTo(self::class, 'supersedes_request_id');
    }

    // 逆参照：この申請を親にもつ再申請一覧（任意）
    public function childRequests()
    {
        return $this->hasMany(self::class, 'supersedes_request_id');
    }

    // ステータス定数
    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    // 便利ヘルパ
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }
}
