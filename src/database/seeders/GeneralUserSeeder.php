<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceBreak;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;

class GeneralUserSeeder extends Seeder
{
    public function run(): void
    {
        $tz = 'Asia/Tokyo';

        // ▼ 日付範囲（例：2025/08/30〜2025/09/05）
        $start = Carbon::create(2025, 8, 30, 0, 0, 0, $tz);
        $end   = Carbon::create(2025, 9, 5,  0, 0, 0, $tz);

        // ▼ 対象ユーザー（増やしたいときは配列に追加）
        $users = [
            [
                'name'  => '一般ユーザー1',
                'email' => 'user1@example.com',
                'role'  => 'user',
                'password' => 'password',
            ],
            [
                'name'  => '一般ユーザー2',
                'email' => 'user2@example.com',
                'role'  => 'user',
                'password' => 'password',
            ],
        ];

        // ユーザー作成または更新
        foreach ($users as &$u) {
            $user = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name'     => $u['name'],
                    'role'     => $u['role'],
                    'password' => Hash::make($u['password']),
                ]
            );
            $u['id'] = $user->id;
        }
        unset($u);

        // 日付ループ
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {

            // 土日を除外したいなら下のifを解除
            // if ($d->isWeekend()) continue;

            foreach ($users as $u) {
                // 勤怠時刻（ランダム微調整したい場合）
                $in   = $d->copy()->setTime(9, 0);
                $out  = $d->copy()->setTime(18, 0);

                // 5〜15分のズレをランダムで付与（任意）
                $in->addMinutes([0, 5, 10, 15][array_rand([0, 1, 2, 3])]);
                $out->subMinutes([0, 5, 10, 15][array_rand([0, 1, 2, 3])]);

                // 勤怠 upsert（ユーザー×日付で1件）
                $att = Attendance::updateOrCreate(
                    ['user_id' => $u['id'], 'work_date' => $d->toDateString()],
                    [
                        'clock_in_at'  => $in,
                        'clock_out_at' => $out,
                        'status'       => 'finished',
                        'note'         => 'Seeder: 自動投入データ',
                    ]
                );

                // 既存の休憩は一旦削除して入れ直し（再実行に強い）
                $att->breaks()->delete();

                // 休憩1（昼休憩 12:00〜13:00）
                AttendanceBreak::create([
                    'attendance_id' => $att->id,
                    'start_at'      => $d->copy()->setTime(12, 0),
                    'end_at'        => $d->copy()->setTime(13, 0),
                ]);

                // 休憩2（午後小休憩 15:00〜15:10〜15:20からランダム）
                $pmStart = $d->copy()->setTime(15, 0)->addMinutes([0, 5, 10][array_rand([0, 1, 2])]);
                $pmEnd   = $pmStart->copy()->addMinutes([10, 15, 20][array_rand([0, 1, 2])]);

                AttendanceBreak::create([
                    'attendance_id' => $att->id,
                    'start_at'      => $pmStart,
                    'end_at'        => $pmEnd,
                ]);
            }
        }
    }
}
