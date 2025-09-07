<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEditRequest;

class EditRequestListController extends Controller
{
    public function pending()
    {
        $rows = AttendanceEditRequest::with(['attendance', 'applicant'])
            ->where('applicant_id', auth()->id())
            ->where('status', AttendanceEditRequest::STATUS_PENDING)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('my.requests.pending', compact('rows'));
    }

    public function approved()
    {
        $rows = AttendanceEditRequest::with(['attendance', 'applicant'])
            ->where('applicant_id', auth()->id())
            ->where('status', AttendanceEditRequest::STATUS_APPROVED)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('my.requests.approved', compact('rows'));
    }

    public function show($id)
    {
        $req = AttendanceEditRequest::with(['attendance', 'applicant'])
            ->where('applicant_id', auth()->id())
            ->findOrFail($id);

        $attendanceId = $req->attendance?->id;

        return view('my.requests.show', compact('req', 'attendanceId'));
    }
}
