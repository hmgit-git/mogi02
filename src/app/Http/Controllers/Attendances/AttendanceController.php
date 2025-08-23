<?php

namespace App\Http\Controllers\Attendances;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

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

        return back()->with('status', '出勤しました。いってらっしゃい！');
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

        return back()->with('status', 'おつかれさまでした！');
    }
}
