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

        // 対象期間
        $start = Carbon::create(2025, 8, 30, 0, 0, 0, $tz);
        $end   = Carbon::create(2025, 9, 5,  0, 0, 0, $tz);

        // 一般ユーザー（3名）
        $users = [
            ['name' => '一般ユーザー1', 'email' => 'user1@example.com', 'role' => 'user', 'password' => 'password'],
            ['name' => '一般ユーザー2', 'email' => 'user2@example.com', 'role' => 'user', 'password' => 'password'],
            ['name' => '一般ユーザー3', 'email' => 'user3@example.com', 'role' => 'user', 'password' => 'password'],
        ];

        // 作成/更新（認証済みに）
        foreach ($users as &$u) {
            $user = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name'              => $u['name'],
                    'role'              => $u['role'],
                    'password'          => Hash::make($u['password']),
                    'email_verified_at' => now(),
                ]
            );
            $u['id'] = $user->id;
        }
        unset($u);

        // 日付ループ
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            foreach ($users as $u) {
                // 出退勤（適度にブレさせる）
                $in  = $d->copy()->setTime(9, 0);
                $out = $d->copy()->setTime(18, 0);

                $minsIn  = [0, 5, 10, 15];
                $minsOut = [0, 5, 10, 15];

                $in->addMinutes($minsIn[array_rand($minsIn)]);
                $out->subMinutes($minsOut[array_rand($minsOut)]);

                // 勤怠 upsert（user_id × work_date で一意）
                $att = Attendance::updateOrCreate(
                    ['user_id' => $u['id'], 'work_date' => $d->toDateString()],
                    [
                        'clock_in_at'  => $in,
                        'clock_out_at' => $out,
                        'status'       => 'finished',
                        'note'         => 'Seeder: 自動投入データ',
                    ]
                );

                // 休憩は入れ直し
                $att->breaks()->delete();

                // 昼休憩 12:00〜13:00
                AttendanceBreak::create([
                    'attendance_id' => $att->id,
                    'start_at'      => $d->copy()->setTime(12, 0),
                    'end_at'        => $d->copy()->setTime(13, 0),
                ]);

                // 午後小休憩（15:00±数分、10〜20分）
                $pmStartCandidates = [0, 5, 10];   // 分
                $pmDurations       = [10, 15, 20]; // 分
                $pmStart = $d->copy()->setTime(15, 0)->addMinutes($pmStartCandidates[array_rand($pmStartCandidates)]);
                $pmEnd   = $pmStart->copy()->addMinutes($pmDurations[array_rand($pmDurations)]);

                AttendanceBreak::create([
                    'attendance_id' => $att->id,
                    'start_at'      => $pmStart,
                    'end_at'        => $pmEnd,
                ]);
            }
        }
    }
}
