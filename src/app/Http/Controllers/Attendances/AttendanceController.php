<?php

namespace App\Http\Controllers\Attendances;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Http\Requests\StoreAttendanceEditRequest;
use App\Models\AttendanceEditRequest;
use Illuminate\Support\Facades\Schema;


class AttendanceController extends Controller
{
    /**
     * ダッシュボード（当日分の状態表示）
     */
    public function index()
    {
        $user  = Auth::user();
        $today = Carbon::now('Asia/Tokyo')->toDateString();

        $att = Attendance::where('user_id', $user->id)
            ->where('work_date', $today)
            ->first();

        // 状態判定（breaksテーブル基準）
        $state = 'not_started';
        if ($att && $att->clock_in_at) {
            if ($att->clock_out_at) {
                $state = 'finished';
            } elseif ($att->breaks()->whereNull('end_at')->exists()) {
                $state = 'on_break';
            } else {
                $state = 'working';
            }
        }

        // ボタン活性
        $canPunchIn    = ($state === 'not_started');
        $canBreakStart = ($state === 'working');
        $canBreakEnd   = ($state === 'on_break');
        $canPunchOut   = in_array($state, ['working', 'on_break'], true);

        return view('attendances.index', compact(
            'today',
            'att',
            'state',
            'canPunchIn',
            'canBreakStart',
            'canBreakEnd',
            'canPunchOut'
        ));
    }

    /**
     * 出勤
     */
    public function punchIn(Request $request)
    {
        $user  = Auth::user();
        $now   = Carbon::now('Asia/Tokyo');
        $today = $now->toDateString();

        try {
            DB::transaction(function () use ($user, $today, $now) {
                $att = Attendance::where('user_id', $user->id)
                    ->where('work_date', $today)
                    ->lockForUpdate()
                    ->first();

                if (!$att) {
                    Attendance::create([
                        'user_id'     => $user->id,
                        'work_date'   => $today,
                        'clock_in_at' => $now,
                        'status'      => 'working',
                    ]);
                    return;
                }

                if (!$att->clock_in_at) {
                    $att->clock_in_at = $now;
                }
                $att->status = 'working';
                $att->save();
            });
        } catch (\Throwable $e) {
            report($e);
            return back()->with('auth_error', '出勤の記録に失敗しました。もう一度お試しください。');
        }

        return back()->with('status');
    }

    /**
     * 休憩開始（何回でもOK）
     */
    public function breakStart(Request $request)
    {
        $user  = Auth::user();
        $now   = Carbon::now('Asia/Tokyo');
        $today = $now->toDateString();

        try {
            DB::transaction(function () use ($user, $today, $now) {
                $att = Attendance::where('user_id', $user->id)
                    ->where('work_date', $today)
                    ->lockForUpdate()
                    ->first();

                if (!$att || !$att->clock_in_at || $att->clock_out_at) {
                    return; // 出勤前 or 退勤後は不可
                }

                // 未終了の休憩があれば何もしない（連打耐性）
                if ($att->breaks()->whereNull('end_at')->exists()) {
                    return;
                }

                $att->breaks()->create(['start_at' => $now]);
                $att->status = 'on_break';
                $att->save();
            });
        } catch (\Throwable $e) {
            report($e);
            return back()->with('auth_error', '休憩開始の記録に失敗しました。');
        }

        return back()->with('status', '休憩に入りました。');
    }

    /**
     * 休憩終了（直近の未終了休憩を閉じる）
     */
    public function breakEnd(Request $request)
    {
        $user  = Auth::user();
        $now   = Carbon::now('Asia/Tokyo');
        $today = $now->toDateString();

        try {
            DB::transaction(function () use ($user, $today, $now) {
                $att = Attendance::where('user_id', $user->id)
                    ->where('work_date', $today)
                    ->lockForUpdate()
                    ->first();

                if (!$att || !$att->clock_in_at || $att->clock_out_at) {
                    return;
                }

                $open = $att->breaks()->whereNull('end_at')->latest('start_at')->first();
                if (!$open) {
                    return; // 休憩中ではない
                }

                $open->end_at = $now;
                $open->save();

                $att->status = 'working';
                $att->save();
            });
        } catch (\Throwable $e) {
            report($e);
            return back()->with('auth_error', '休憩終了の記録に失敗しました。');
        }

        return back()->with('status', '休憩から戻りました。');
    }

    /**
     * 退勤（未終了の休憩があれば閉じてから退勤）
     */
    public function punchOut(Request $request)
    {
        $user  = Auth::user();
        $now   = Carbon::now('Asia/Tokyo');
        $today = $now->toDateString();

        try {
            DB::transaction(function () use ($user, $today, $now) {
                $att = Attendance::where('user_id', $user->id)
                    ->where('work_date', $today)
                    ->lockForUpdate()
                    ->first();

                if (!$att || !$att->clock_in_at || $att->clock_out_at) {
                    return; // 出勤していない or 既に退勤済み
                }

                // 未終了の休憩があれば今で閉じる
                $open = $att->breaks()->whereNull('end_at')->latest('start_at')->first();
                if ($open) {
                    $open->end_at = $now;
                    $open->save();
                }

                $att->clock_out_at = $now;
                $att->status = 'finished';
                $att->save();
            });
        } catch (\Throwable $e) {
            report($e);
            return back()->with('auth_error', '退勤の記録に失敗しました。');
        }

        return back()->with('status', 'お疲れ様でした。');
    }

    /**
     * 月次一覧
     */
    public function list(Request $request)
    {
        $user = Auth::user();

        // month=YYYY-MM（未指定なら今月）
        $monthStr = $request->query('month');
        if (!$monthStr || !preg_match('/^\d{4}-\d{2}$/', $monthStr)) {
            $monthStr = Carbon::now('Asia/Tokyo')->format('Y-m');
        }

        $month  = Carbon::createFromFormat('Y-m', $monthStr, 'Asia/Tokyo')->startOfMonth();
        $start  = $month->copy()->startOfMonth();
        $end    = $month->copy()->endOfMonth();
        $today0 = Carbon::now('Asia/Tokyo')->startOfDay();

        // その月の勤怠＋休憩＋（自分の承認待ち申請だけ）を取得
        $atts = Attendance::where('user_id', $user->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->with([
                'breaks',
                // ★ 追加：自分の pending だけを一緒に読む（最小カラム）
                'editRequests' => function ($q) use ($user) {
                    $q->where('applicant_id', $user->id)
                        ->where('status', \App\Models\AttendanceEditRequest::STATUS_PENDING)
                        ->select('id', 'attendance_id');
                },
            ])
            ->get()
            ->keyBy(fn($a) => $a->work_date->toDateString());

        $days = [];
        $totalWorked = 0;

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key   = $d->toDateString();
            $att   = $atts->get($key);

            // 表示を MM/DD(曜) に
            $youbiMap = ['日', '月', '火', '水', '木', '金', '土'];
            $label    = $d->format('m/d') . '(' . $youbiMap[$d->dayOfWeek] . ')';

            $clockIn  = $att?->clock_in_at;
            $clockOut = $att?->clock_out_at;

            if ($att) {
                // 退勤していなければ、当日なら「今」、過去日なら「その日の終わり」までで集計
                $asOf = $clockOut ?: ($d->lt($today0) ? $d->copy()->endOfDay() : Carbon::now('Asia/Tokyo'));

                $breakMin = $att->breaks->sum(function ($br) use ($asOf) {
                    if (!$br->start_at) return 0;
                    $end = $br->end_at ?: $asOf;
                    return $br->start_at->diffInMinutes($end);
                });

                $worked = $clockIn ? max(0, $clockIn->diffInMinutes($asOf) - $breakMin) : 0;
                $totalWorked += $worked;
            } else {
                $breakMin = 0;
                $worked   = 0;
            }

            // ★ 追加：承認待ち申請が1件でもあれば、そのIDを拾う
            $pendingReqId = $att?->editRequests?->first()?->id;

            $days[] = [
                'date'           => $key,
                'label'          => $label,
                'att_id'         => $att->id ?? null,
                'clock_in'       => $clockIn  ? $clockIn->format('H:i')  : '-',
                'clock_out'      => $clockOut ? $clockOut->format('H:i') : '-',
                'break_min'      => $breakMin,
                'work_min'       => $worked,
                'status'         => $att->status ?? '',
                'pending_req_id' => $pendingReqId, // ★ ビューで使う
            ];
        }

        $prevMonth  = $start->copy()->subMonth()->format('Y-m');
        $nextMonth  = $start->copy()->addMonth()->format('Y-m');
        $monthLabel = $start->format('Y/m');

        return view('attendances.list', [
            'month'            => $start->format('Y-m'),
            'days'             => $days,
            'totalWorkMinutes' => $totalWorked,
            'prevMonth'        => $prevMonth,
            'nextMonth'        => $nextMonth,
            'monthLabel'       => $monthLabel,
        ]);
    }

    /**
     * 詳細（ID指定）
     */
    public function detail($id)
    {
        $user = Auth::user();

        $att = Attendance::with('breaks')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $date  = Carbon::parse($att->work_date, 'Asia/Tokyo');
        $youbi = ['日', '月', '火', '水', '木', '金', '土'][$date->dayOfWeek];

        $asOf = $att->clock_out_at
            ?: ($date->isPast() ? $date->copy()->endOfDay() : Carbon::now('Asia/Tokyo'));

        $breakMinutes = $att->breaks->sum(function ($br) use ($asOf) {
            if (!$br->start_at) return 0;
            $end = $br->end_at ?: $asOf;
            return $br->start_at->diffInMinutes($end);
        });

        $workedMinutes = $att->clock_in_at
            ? max(0, $att->clock_in_at->diffInMinutes($asOf) - $breakMinutes)
            : 0;

        return view('attendances.detail', [
            'att'            => $att,
            'dateLabel'      => $date->format('Y年n月j日') . "($youbi)",
            'breakMinutes'   => $breakMinutes,
            'workedMinutes'  => $workedMinutes,
            'monthParam'     => $date->format('Y-m'),
        ]);
    }

    /**
     * 詳細（日付指定：レコードが無くても開ける）
     */
    public function detailByDate(string $date)
    {
        $user = Auth::user();
        $d    = Carbon::createFromFormat('Y-m-d', $date, 'Asia/Tokyo')->startOfDay();

        $att = Attendance::with('breaks')
            ->where('user_id', $user->id)
            ->whereDate('work_date', $d->toDateString())
            ->first(); // 無い日もOK

        $youbi = ['日', '月', '火', '水', '木', '金', '土'][$d->dayOfWeek];

        $asOf = $att?->clock_out_at
            ?: ($d->isPast() ? $d->copy()->endOfDay() : Carbon::now('Asia/Tokyo'));

        $breakMinutes = $att
            ? $att->breaks->sum(function ($br) use ($asOf) {
                if (!$br->start_at) return 0;
                $end = $br->end_at ?: $asOf;
                return $br->start_at->diffInMinutes($end);
            })
            : 0;

        $workedMinutes = ($att && $att->clock_in_at)
            ? max(0, $att->clock_in_at->diffInMinutes($asOf) - $breakMinutes)
            : 0;

        return view('attendances.detail', [
            'att'            => $att,
            'dateLabel'      => $d->format('Y年n月j日') . "($youbi)",
            'breakMinutes'   => $breakMinutes,
            'workedMinutes'  => $workedMinutes,
            'monthParam'     => $d->format('Y-m'),
            'breaks'         => $att ? $att->breaks->sortBy('start_at') : collect(),
        ]);
    }
    public function requestUpdate(StoreAttendanceEditRequest $request)
    {
        // H:i を Y-m-d H:i に合成
        $clockIn  = $request->mergedDateTime($request->input('clock_in_at'));
        $clockOut = $request->mergedDateTime($request->input('clock_out_at'));

        $breaksInput = $request->input('breaks', []);
        $tz = 'Asia/Tokyo';
        $date = $request->input('date');

        $breaks = [];
        foreach ($breaksInput as $b) {
            $s = $b['start_at'] ?? null;
            $e = $b['end_at'] ?? null;
            if (!$s && !$e) continue; // 両方空はスキップ
            $breaks[] = [
                'start_at' => $s ? \Illuminate\Support\Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $s, $tz)->toDateTimeString() : null,
                'end_at'   => $e ? \Illuminate\Support\Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $e, $tz)->toDateTimeString()   : null,
            ];
        }

        $changes = [
            'clock_in_at'  => $clockIn,
            'clock_out_at' => $clockOut,
            'breaks'       => array_values($breaks),
        ];

        \App\Models\AttendanceEditRequest::create([
            'attendance_id'     => $request->input('attendance_id'),
            'applicant_id'      => auth()->id(),
            'requested_changes' => $changes,
            'reason'            => $request->input('reason'),
            'status'            => \App\Models\AttendanceEditRequest::STATUS_PENDING,
        ]);

        return redirect()
            ->route('attendance.detail', $request->input('attendance_id'))
            ->with('status', '修正申請を送信しました。');
    }
}
