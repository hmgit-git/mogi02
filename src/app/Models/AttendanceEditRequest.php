<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceEditRequest extends Model
{
    use HasFactory;

    /* ===== ステータス定数 ===== */
    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /* デフォルト値 */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    /* ===== クエリスコープ ===== */
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

    /* ===== プロパティ ===== */
    protected $fillable = [
        'attendance_id',
        'user_id',           // 申請者
        'req_clock_in_at',
        'req_clock_out_at',
        'requested_breaks',
        'reason',
        'status',            // pending / approved / rejected
        'reviewed_by',       // 承認者
        'reviewed_at',
        'review_note',
    ];

    protected $casts = [
        'req_clock_in_at'   => 'datetime',
        'req_clock_out_at'  => 'datetime',
        'requested_breaks'  => 'array',
        'reviewed_at'       => 'datetime',
    ];

    /* ===== リレーション ===== */
    /** 勤怠 */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /** 申請者 */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 承認者 */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /* ===== 便利メソッド ===== */
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
