<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\AttendanceEditRequest as EditReq;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceEditRequestController extends Controller
{
    /** ①一覧：承認待ち/承認済みタブ */
    public function index(Request $request)
    {
        $status = $request->query('status', EditReq::STATUS_PENDING); // 'pending' or 'approved'
        $requests = EditReq::with(['user', 'attendance.user', 'reviewer'])
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.requests.index', compact('requests', 'status'));
    }

    /** ②詳細：選択行と一致する内容を表示 */
    public function show($id)
    {
        $r = EditReq::with(['user', 'attendance.breaks', 'attendance.user', 'reviewer'])->findOrFail($id);

        // 対象日（勤怠が無い申請でも見れるように推定）
        $workDate = $r->attendance?->work_date
            ?? ($r->req_clock_in_at?->copy()->timezone('Asia/Tokyo')->toDateString()
                ?? $r->req_clock_out_at?->copy()->timezone('Asia/Tokyo')->toDateString());

        return view('admin.requests.show', [
            'req'      => $r,
            'workDate' => $workDate,
        ]);
    }

    /** ③承認：勤怠へ反映 → 申請を承認済みに */
    public function approve($id, Request $request)
    {
        $req = EditReq::with(['attendance', 'user'])->findOrFail($id);

        if ($req->isApproved()) {
            return back()->with('status', 'すでに承認済みです。');
        }

        DB::transaction(function () use ($req, $request) {
            $tz = 'Asia/Tokyo';

            // 対象日を確定
            $workDate = $req->attendance?->work_date
                ?? ($req->req_clock_in_at?->copy()->timezone($tz)->toDateString()
                    ?? $req->req_clock_out_at?->copy()->timezone($tz)->toDateString());

            if (!$workDate) {
                // どちらも無い場合は申請に紐づく勤怠が必須
                throw new \RuntimeException('対象日を特定できません。');
            }

            // 勤怠レコードを upsert（ユーザーID × 日付で1件）
            $att = Attendance::firstOrCreate(
                ['user_id' => $req->user_id, 'work_date' => $workDate],
                ['status' => 'not_started']
            );

            // 出退勤の反映
            if ($req->req_clock_in_at)  $att->clock_in_at  = $req->req_clock_in_at->copy()->timezone($tz);
            if ($req->req_clock_out_at) $att->clock_out_at = $req->req_clock_out_at->copy()->timezone($tz);

            // 休憩の反映（申請に含まれていれば置き換え）
            if (is_array($req->requested_breaks)) {
                // 既存を入れ替え（要件に応じてマージでもOK）
                $att->breaks()->delete();
                foreach ($req->requested_breaks as $br) {
                    $start = isset($br['start_at']) ? Carbon::parse($br['start_at'], $tz) : null;
                    $end   = isset($br['end_at'])   ? Carbon::parse($br['end_at'], $tz)   : null;
                    if ($start) {
                        $att->breaks()->create([
                            'start_at' => $start,
                            'end_at'   => $end,
                        ]);
                    }
                }
            }

            // ステータス更新（退勤があれば finished、なければ working）
            $att->status = $att->clock_out_at ? 'finished' : ($att->clock_in_at ? 'working' : 'not_started');
            $att->save();

            // 申請を承認済みに
            $req->update([
                'status'      => EditReq::STATUS_APPROVED,
                'reviewed_by' => auth('admin')->id(),
                'reviewed_at' => Carbon::now($tz),
                'review_note' => $request->string('note')->toString() ?: null,
            ]);
        });

        // 要件 ②-承認: 一覧の“承認待ち”から“承認済み”へ / 勤怠も更新済み
        return redirect()
            ->route('admin.requests.index', ['status' => EditReq::STATUS_PENDING])
            ->with('status', '承認しました。');
    }
}
