<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\Admin\UpdateAttendanceRequest;


class AdminAttendanceController extends Controller
{
    public function daily(Request $request)
    {
        $tz   = 'Asia/Tokyo';
        $date = $request->query('date'); // YYYY-MM-DD 期待
        $d    = $date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            ? \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $date, $tz)
            : \Illuminate\Support\Carbon::now($tz);
        $d    = $d->startOfDay();

        // 当日の全ユーザーの勤怠＋休憩
        $atts = \App\Models\Attendance::with(['user', 'breaks'])
            ->whereDate('work_date', $d->toDateString())
            ->get()
            ->sortBy(fn($a) => $a->user?->name ?? ''); 

        $rows = [];
        $now  = \Illuminate\Support\Carbon::now($tz);

        foreach ($atts as $att) {
            $asOf = $att->clock_out_at ?: ($d->isToday() ? $now : $d->copy()->endOfDay());

            $breakMin = $att->breaks->sum(function ($br) use ($asOf) {
                if (!$br->start_at) return 0;
                $end = $br->end_at ?: $asOf;
                return $br->start_at->diffInMinutes($end);
            });

            $workMin = $att->clock_in_at
                ? max(0, $att->clock_in_at->diffInMinutes($asOf) - $breakMin)
                : 0;

            $rows[] = [
                'id'        => $att->id,
                'name'      => $att->user?->name ?? '-',
                'clock_in'  => $att->clock_in_at  ? $att->clock_in_at->format('H:i')  : '-',
                'clock_out' => $att->clock_out_at ? $att->clock_out_at->format('H:i') : '-',
                'break_hm'  => self::minToHm($breakMin),
                'work_hm'   => self::minToHm($workMin),
            ];
        }

        // 見出し・ナビ（日次）
        $dateTitle = $d->format('Y年n月j日');
        $centerYmd = $d->format('Y/m/d');
        $prevDate  = $d->copy()->subDay()->format('Y-m-d'); // ← 前日
        $nextDate  = $d->copy()->addDay()->format('Y-m-d'); // ← 翌日

        return view('admin.attendances.daily', compact(
            'rows',
            'dateTitle',
            'centerYmd',
            'prevDate',
            'nextDate',
            'd'
        ));
    }

    /** 詳細 */
    public function show($id)
    {
        $tz  = 'Asia/Tokyo';
        $att = Attendance::with(['user', 'breaks'])->findOrFail($id);

        $date  = Carbon::parse($att->work_date, $tz);
        $youbi = ['日', '月', '火', '水', '木', '金', '土'][$date->dayOfWeek];

        // 集計用
        $asOf = $att->clock_out_at ?: ($date->isToday() ? Carbon::now($tz) : $date->copy()->endOfDay());

        $breakMin = $att->breaks->sum(function ($br) use ($asOf) {
            if (!$br->start_at) return 0;
            $end = $br->end_at ?: $asOf;
            return $br->start_at->diffInMinutes($end);
        });
        $workMin = $att->clock_in_at ? max(0, $att->clock_in_at->diffInMinutes($asOf) - $breakMin) : 0;

        return view('admin.attendances.show', [
            'att'           => $att,
            'dateLabel'     => $date->format('Y年n月j日') . "($youbi)",
            'breakMinutes'  => $breakMin,
            'workedMinutes' => $workMin,
        ]);
    }

    /** 分→H:MM */
    private static function minToHm(int $min): string
    {
        $h = intdiv($min, 60);
        $m = $min % 60;
        return sprintf('%d:%02d', $h, $m);
    }

    public function update(UpdateAttendanceRequest $request, $id)
    {
        $att = \App\Models\Attendance::with('breaks', 'user')->findOrFail($id);
        $tz = 'Asia/Tokyo';
        $workDate = $att->work_date->toDateString();

        $toDateTime = function (?string $hm) use ($workDate, $tz) {
            if (!$hm) return null;
            return \Illuminate\Support\Carbon::createFromFormat('Y-m-d H:i', $workDate . ' ' . $hm, $tz);
        };

        $clockIn  = $toDateTime($request->input('clock_in_at'));
        $clockOut = $toDateTime($request->input('clock_out_at'));

        $breaksIn = collect($request->input('breaks', []))
            ->map(fn($row) => [
                'start_at' => $toDateTime($row['start_at'] ?? null),
                'end_at'   => $toDateTime($row['end_at']   ?? null),
            ])
            ->values();

        \DB::transaction(function () use ($att, $clockIn, $clockOut, $breaksIn, $request) {
            $att->clock_in_at  = $clockIn;
            $att->clock_out_at = $clockOut;

            $att->breaks()->delete();
            foreach ($breaksIn as $b) {
                if (!$b['start_at'] && !$b['end_at']) continue;
                $att->breaks()->create([
                    'start_at' => $b['start_at'],
                    'end_at'   => $b['end_at'],
                ]);
            }

            $att->status = $att->clock_out_at
                ? 'finished'
                : ($att->clock_in_at ? 'working' : 'not_started');

            // 備考は必須なので必ず上書き
            $att->note = trim((string)$request->input('reason', ''));

            $att->save();
        });

        return back()->with('status', '勤怠を保存しました。');
    }
}
