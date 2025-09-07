<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceEditRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'attendance_id',
        'applicant_id',
        'requested_changes',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected $casts = [
        'requested_changes' => 'array',
        'reviewed_at'       => 'datetime',
    ];

    /* ========= スコープ（任意） ========= */
    public function scopePending($q)
    {
        return $q->where('status', self::STATUS_PENDING);
    }
    public function scopeApproved($q)
    {
        return $q->where('status', self::STATUS_APPROVED);
    }
    public function scopeRejected($q)
    {
        return $q->where('status', self::STATUS_REJECTED);
    }

    /* ========= リレーション ========= */

    /** 勤怠 */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /** 申請者（一般ユーザー） */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    /** 承認者（管理者）*/
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /* ========= 便利ヘルパ ========= */
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
