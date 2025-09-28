<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminStaffController extends Controller
{
    /** スタッフ一覧（検索＋ページング） */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->where('role', '!=', 'admin') // 一般ユーザーのみ
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString(); // ← 検索クエリをページネーションに引き継ぐ

        return view('admin.staff.index', compact('users', 'q'));
    }

    /** スタッフ詳細（必要なら） */
    public function show(User $user)
    {
        return view('admin.staff.show', compact('user'));
    }

    /** スタッフ別 月次勤怠一覧 */
    public function monthly(User $user, Request $request)
    {
        $tz = 'Asia/Tokyo';

        // month=YYYY-MM 未指定なら当月
        $monthStr = $request->query('month');
        if (!$monthStr || !preg_match('/^\d{4}-\d{2}$/', $monthStr)) {
            $monthStr = Carbon::now($tz)->format('Y-m');
        }

        $month = Carbon::createFromFormat('Y-m', $monthStr, $tz)->startOfMonth();
        $start = $month->copy()->startOfMonth();
        $end   = $month->copy()->endOfMonth();
        $today0 = Carbon::now($tz)->startOfDay();

        // 月内勤怠＆休憩まとめて取得
        $atts = Attendance::where('user_id', $user->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->with('breaks')
            ->get()
            ->keyBy(fn($a) => $a->work_date->toDateString());

        $rows = [];
        $totalWorked = 0;

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->toDateString();
            $att = $atts->get($key);

            $label = $d->format('m/d');
            $clockIn  = $att?->clock_in_at;
            $clockOut = $att?->clock_out_at;

            if ($att) {
                $asOf = $clockOut ?: ($d->lt($today0) ? $d->copy()->endOfDay() : Carbon::now($tz));
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

            $rows[] = [
                'date'      => $key,
                'label'     => $label,
                'att_id'    => $att->id ?? null,
                'clock_in'  => $clockIn  ? $clockIn->format('H:i')  : '',
                'clock_out' => $clockOut ? $clockOut->format('H:i') : '',
                'break_min' => $breakMin,
                'work_min'  => $worked,
            ];
        }

        $prevMonth  = $start->copy()->subMonth()->format('Y-m');
        $nextMonth  = $start->copy()->addMonth()->format('Y-m');
        $monthLabel = $start->format('Y/m');

        return view('admin.staff.attendances', [
            'staff'            => $user,
            'month'            => $start->format('Y-m'),
            'monthLabel'       => $monthLabel,
            'prevMonth'        => $prevMonth,
            'nextMonth'        => $nextMonth,
            'rows'             => $rows,
            'totalWorkMinutes' => $totalWorked,
        ]);
    }

    /** CSV 出力（選択月） */
    public function exportCsv(User $user, Request $request): StreamedResponse
    {
        $tz = 'Asia/Tokyo';
        $monthStr = $request->query('month');
        if (!$monthStr || !preg_match('/^\d{4}-\d{2}$/', $monthStr)) {
            $monthStr = Carbon::now($tz)->format('Y-m');
        }
        $month  = Carbon::createFromFormat('Y-m', $monthStr, $tz)->startOfMonth();
        $start  = $month->copy()->startOfMonth();
        $end    = $month->copy()->endOfMonth();
        $today0 = Carbon::now($tz)->startOfDay();

        $atts = Attendance::where('user_id', $user->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->with('breaks')
            ->get()
            ->keyBy(fn($a) => $a->work_date->toDateString());

        $filename = sprintf('attendance_%s_%s.csv', $user->id, $month->format('Ym'));

        return response()->streamDownload(function () use ($atts, $start, $end, $today0, $tz) {
            $out = fopen('php://output', 'w');

            // ヘッダ
            fputcsv($out, ['日付', '出勤', '退勤', '休憩(分)', '実働(分)']);

            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $key = $d->toDateString();
                $att = $atts->get($key);

                if ($att) {
                    $clockIn  = $att->clock_in_at;
                    $clockOut = $att->clock_out_at;
                    $asOf = $clockOut ?: ($d->lt($today0) ? $d->copy()->endOfDay() : Carbon::now($tz));
                    $breakMin = $att->breaks->sum(function ($br) use ($asOf) {
                        if (!$br->start_at) return 0;
                        $end = $br->end_at ?: $asOf;
                        return $br->start_at->diffInMinutes($end);
                    });
                    $worked = $clockIn ? max(0, $clockIn->diffInMinutes($asOf) - $breakMin) : 0;

                    fputcsv($out, [
                        $d->format('Y-m-d'),
                        $clockIn  ? $clockIn->format('H:i')  : '',
                        $clockOut ? $clockOut->format('H:i') : '',
                        $breakMin,
                        $worked,
                    ]);
                } else {
                    fputcsv($out, [$d->format('Y-m-d'), '', '', 0, 0]);
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
