<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceEditApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_edit_request_id',
        'approver_id',
        'decision',
        'comment',
        'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    /** 承認が属する修正申請 */
    public function request()
    {
        return $this->belongsTo(AttendanceEditRequest::class, 'attendance_edit_request_id');
    }

    /** 承認者（User） */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
