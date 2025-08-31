<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEditRequest as EditReq;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceEditRequestController extends Controller
{
    // 一覧（承認待ちデフォルト）
    public function index(Request $request)
    {
        $status = $request->query('status', EditReq::STATUS_PENDING); // pending|approved|rejected

        $requests = EditReq::with(['user', 'attendance.user', 'reviewer'])
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.requests.index', compact('requests', 'status'));
    }

    // 承認
    public function approve($id, Request $request)
    {
        $req = EditReq::findOrFail($id);

        if ($req->isApproved()) {
            return back()->with('status', 'すでに承認済みです。');
        }

        $req->update([
            'status'      => EditReq::STATUS_APPROVED,
            'reviewed_by' => auth('admin')->id(),
            'reviewed_at' => Carbon::now('Asia/Tokyo'),
            'review_note' => $request->string('note')->toString() ?: null,
        ]);

        return back()->with('status', '承認しました。');
    }

    // 却下
    public function reject($id, Request $request)
    {
        $req = EditReq::findOrFail($id);

        if ($req->isRejected()) {
            return back()->with('status', 'すでに却下済みです。');
        }

        $req->update([
            'status'      => EditReq::STATUS_REJECTED,
            'reviewed_by' => auth('admin')->id(),
            'reviewed_at' => Carbon::now('Asia/Tokyo'),
            'review_note' => $request->string('note')->toString() ?: null,
        ]);

        return back()->with('status', '却下しました。');
    }
}
