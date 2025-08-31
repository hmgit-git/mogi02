<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;                  
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class AdminAttendanceController extends Controller
{
    /** 日次一覧（見出し・前月/翌月ナビ・表） */
    public function daily(Request $request)
    {
        $tz   = 'Asia/Tokyo';
        $date = $request->query('date');
        $d    = $date ? Carbon::createFromFormat('Y-m-d', $date, $tz) : Carbon::now($tz);
        $d    = $d->startOfDay();

        // 当日の全ユーザーの勤怠＋休憩
        $atts = Attendance::query()
            ->with(['user:id,name', 'breaks']) // 余計な列を抑える
            ->whereDate('work_date', $d->toDateString())
            ->orderBy(
                User::select('name')
                    ->whereColumn('users.id', 'attendances.user_id')
            )
            ->orderBy('attendances.id') 
            ->get();

        $rows = [];
        $now  = Carbon::now($tz);
        foreach ($atts as $att) {
            // 退勤してなければ当日なら今、過去日ならその日の終わりまでで集計
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

        // 見出し・ナビ
        $dateTitle = $d->format('Y年n月j日');
        $centerYmd = $d->format('Y/m/d');
        $prevDate  = $d->copy()->subMonth()->format('Y-m-d'); // 要件の「前月/翌月」
        $nextDate  = $d->copy()->addMonth()->format('Y-m-d');

        return view('admin.attendances.daily', compact('rows', 'dateTitle', 'centerYmd', 'prevDate', 'nextDate', 'd'));
    }

    /** 詳細（ユーザーUIと同テイスト） */
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
}
