<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceEditRequest as EditReq;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceEditRequestController extends Controller
{
    /** 一覧（承認待ち/承認済みタブ） */
    public function index(Request $request)
    {
        $status = $request->query('status', EditReq::STATUS_PENDING);

        $requests = EditReq::with(['applicant', 'attendance.user', 'reviewer'])
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.requests.index', compact('requests', 'status'));
    }

    /** 詳細（現行値 vs 申請JSONの比較用） */
    public function show($id)
    {
        $tz = 'Asia/Tokyo';

        $r = EditReq::with(['applicant', 'attendance.breaks', 'attendance.user', 'reviewer'])
            ->findOrFail($id);

        $changes = $r->requested_changes ?? [];

        // 対象日：勤怠に紐付いていればそれ優先、無ければ申請の出退勤から推定
        $workDate = $r->attendance?->work_date
            ?? ($changes['clock_in_at'] ?? $changes['clock_out_at'] ?? null);
        $workDate = $workDate ? Carbon::parse($workDate, $tz)->toDateString() : null;

        return view('admin.requests.show', [
            'req'      => $r,
            'changes'  => $changes,
            'workDate' => $workDate,
        ]);
    }

    /** 承認：JSON/通常どちらでもOK、勤怠へ反映して承認済みにする */
    public function approve($id, Request $request)
    {
        $tz = 'Asia/Tokyo';

        try {
            $req = EditReq::with(['attendance', 'applicant', 'reviewer'])->findOrFail($id);

            if ($req->isApproved()) {
                return $this->approveResponse($request, $req, 'すでに承認済みです。');
            }

            $changes = $req->requested_changes ?? [];

            // 対象日の決定（勤怠>申請のclock_in/out）
            $workDate = $req->attendance?->work_date
                ?? ($changes['clock_in_at'] ?? $changes['clock_out_at'] ?? null);
            if ($workDate) {
                $workDate = Carbon::parse($workDate, $tz)->toDateString();
            }

            if (!$workDate) {
                return $this->approveError($request, '対象日を特定できませんでした。', 422);
            }

            // H:i or 日付付き文字列 → Carbon（対象日の年月日と合成）
            $toDateTime = function ($val) use ($workDate, $tz) {
                if (!$val) return null;
                if (preg_match('/^\d{1,2}:\d{2}$/', $val)) {
                    return Carbon::createFromFormat('Y-m-d H:i', $workDate . ' ' . $val, $tz);
                }
                return Carbon::parse($val, $tz);
            };

            $clockIn  = $toDateTime($changes['clock_in_at']  ?? null);
            $clockOut = $toDateTime($changes['clock_out_at'] ?? null);

            $breaksIn = collect($changes['breaks'] ?? [])
                ->map(fn($b) => [
                    'start_at' => $toDateTime($b['start_at'] ?? null),
                    'end_at'   => $toDateTime($b['end_at']   ?? null),
                ])
                ->filter(fn($b) => $b['start_at'] || $b['end_at'])
                ->values();

            // 要件どおりの論理チェック
            $errors = [];
            if ($clockIn && $clockOut && $clockOut->lt($clockIn)) {
                $errors[] = '出勤時間もしくは退勤時間が不適切な値です';
            }
            foreach ($breaksIn as $b) {
                $s = $b['start_at'];
                $e = $b['end_at'];
                if ($s && $clockIn && $s->lt($clockIn)) {
                    $errors[] = '休憩時間が不適切な値です';
                    break;
                }
                if ($e && $clockOut && $e->gt($clockOut)) {
                    $errors[] = '休憩時間もしくは退勤時間が不適切な値です';
                    break;
                }
                if ($s && $e && $e->lt($s)) {
                    $errors[] = '休憩時間が不適切な値です';
                    break;
                }
            }
            if ($req->reason === null || trim($req->reason) === '') {
                $errors[] = '備考を記入してください';
            }
            if ($errors) {
                return $this->approveError($request, $errors, 422);
            }

            DB::transaction(function () use ($req, $clockIn, $clockOut, $breaksIn, $workDate, $tz, $request) {
                // 勤怠 upsert（ユーザー×日付1件）
                $att = $req->attendance
                    ?: Attendance::firstOrCreate(
                        ['user_id' => $req->applicant_id, 'work_date' => $workDate],
                        ['status' => 'not_started']
                    );

                // 出退勤
                if ($clockIn)  $att->clock_in_at  = $clockIn;
                if ($clockOut) $att->clock_out_at = $clockOut;

                // 休憩は置き換え
                if ($breaksIn->count()) {
                    $att->breaks()->delete();
                    foreach ($breaksIn as $b) {
                        $att->breaks()->create($b);
                    }
                }

                // メモを勤怠 note にも残したい場合（任意）
                if ($req->reason && trim($req->reason) !== '') {
                    $att->note = $req->reason;
                }

                // ステータス更新
                $att->status = $att->clock_out_at
                    ? 'finished'
                    : ($att->clock_in_at ? 'working' : 'not_started');
                $att->save();

                // 申請の承認フラグ
                $req->update([
                    'status'      => EditReq::STATUS_APPROVED,
                    'reviewed_by' => auth('admin')->id(),
                    'reviewed_at' => Carbon::now($tz),
                    'review_note' => $request->input('note') ?: null,
                ]);
            });

            $req->refresh();

            return $this->approveResponse($request, $req, '承認しました。');
        } catch (\Throwable $e) {
            report($e);
            return $this->approveError($request, 'サーバーエラーが発生しました。', 500);
        }
    }

    /** 承認成功の共通レスポンス（JSON/通常） */
    private function approveResponse(Request $request, EditReq $req, string $message)
    {
        $tz = 'Asia/Tokyo';

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'ok'          => true,
                'status'      => $req->status,
                'reviewed_at' => optional($req->reviewed_at)->timezone($tz)->format('Y-m-d H:i'),
                'reviewer'    => optional($req->reviewer)->name,
                'message'     => $message,
            ]);
        }
        return back()->with('status', $message);
    }

    /** 承認失敗の共通レスポンス（JSON/通常） */
    private function approveError(Request $request, $errors, int $code = 400)
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'ok'     => false,
                'errors' => is_array($errors) ? $errors : [$errors],
            ], $code);
        }
        return back()->withErrors(['_top' => is_array($errors) ? $errors : [$errors]]);
    }
}
