<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceBreak extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_id',
        'start_at',
        'end_at',
    ];

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    protected $casts = [
        'start_at' => 'datetime',
        'end_at'   => 'datetime',
    ];
}
