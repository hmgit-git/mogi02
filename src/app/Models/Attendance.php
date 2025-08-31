<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'work_date',
        'clock_in_at',
        'clock_out_at',
        'note',
        'status',
    ];

    protected $casts = [
        'work_date'     => 'date',
        'clock_in_at'   => 'datetime',
        'clock_out_at'  => 'datetime',
    ];

    /** ユーザー */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 勤怠修正申請（1対多） */
    public function editRequests(): HasMany
    {
        // 同一 namespace(App\Models) なのでクラス参照はこれでOK
        return $this->hasMany(AttendanceEditRequest::class);
    }

    /** 休憩（1対多） */
    public function breaks(): HasMany
    {
        return $this->hasMany(AttendanceBreak::class);
    }

    /* ===== 便利スコープ／ヘルパ（任意） ===== */

    /** 未終了の休憩が存在するか */
    public function hasOpenBreak(): bool
    {
        return $this->breaks()->whereNull('end_at')->exists();
    }

    /** 状態ヘルパ */
    public function isWorking(): bool
    {
        return $this->status === 'working';
    }
    public function isOnBreak(): bool
    {
        return $this->status === 'on_break';
    }
    public function isFinished(): bool
    {
        return $this->status === 'finished';
    }
}
