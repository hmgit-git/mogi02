<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    protected $fillable = [
        'user_id',
        'work_date',
        'clock_in_at',
        'break_start_at',
        'break_end_at',
        'clock_out_at',
        'note',
        'status'
    ];
    protected $casts = [
        'work_date' => 'date',
        'clock_in_at' => 'datetime',
        'break_start_at' => 'datetime',
        'break_end_at' => 'datetime',
        'clock_out_at' => 'datetime',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function editRequests()
    {
        return $this->hasMany(AttendanceEditRequest::class);
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(\App\Models\AttendanceBreak::class);
    }
}
