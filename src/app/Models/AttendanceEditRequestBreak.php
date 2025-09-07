<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceEditRequestBreak extends Model
{
    use HasFactory;

    protected $fillable = [
        'edit_request_id',
        'start_at',
        'end_at',
        'order_no',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at'   => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(AttendanceEditRequest::class, 'edit_request_id');
    }
}
