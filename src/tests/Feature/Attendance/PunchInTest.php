<?php

namespace Tests\Feature\Attendance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Support\Facades\DB;

class PunchInTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 出勤ボタンが正しく機能する
     * 期待: 出勤ボタンが表示され、打刻後にステータスが「出勤中（or 勤務中）」になる
     */
    public function test_出勤ボタンが正しく機能する()
    {
        // ▼ 画面が期待するラベル（プロジェクトで「勤務中」を使っているならここを '勤務中' に変えてください）
        $WORKING_LABEL = '出勤中';

        // 時刻固定（JST）
        $now = Carbon::create(2025, 9, 1, 9, 15, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        // 検証済みユーザーでログイン
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        // 勤務外（当日の勤怠なし）で /attendance
        $this->get('/attendance')
            ->assertOk()
            ->assertSee('出勤', false); // 出勤ボタンが見える

        // 出勤POST
        $this->post('/attendance/in')->assertRedirect();

        // もう一度表示して、ステータスが勤務中になっていること
        $this->get('/attendance')
            ->assertOk()
            ->assertSee($WORKING_LABEL, false);
    }

    /**
     * 出勤は一日一回のみできる
     * 期待: 退勤済の当日レコードがある場合、出勤ボタンが表示されない
     */
    public function test_出勤は一日一回のみできる()
    {
        $now = Carbon::create(2025, 9, 1, 18, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        // 当日すでに「出勤→退勤済み」の勤怠を用意
        // モデルがある前提。もし AttendanceFactory が無ければ DB::table で直接 insert でもOK
        Attendance::query()->create([
            'user_id'      => $user->id,
            'work_date'    => $now->toDateString(),                 // 2025-09-01
            'clock_in_at'  => $now->copy()->setTime(9, 0, 0),       // 09:00
            'clock_out_at' => $now->copy()->setTime(18, 0, 0),      // 18:00
            // 必要なら他のカラムも追加
        ]);

        // 打刻画面に「出勤」ボタンが出ていないこと
        $this->get('/attendance')
            ->assertOk()
            ->assertDontSee('出勤', false);
    }

    /**
     * 出勤時刻が勤怠一覧画面で確認できる
     * 期待: 出勤後、/attendance/list に当日の出勤時刻が表示される
     */
    public function test_出勤時刻が勤怠一覧で確認できる()
    {
        // 9:12 に出勤した体で固定
        $now = Carbon::create(2025, 9, 1, 9, 12, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        // 出勤
        $this->post('/attendance/in')->assertRedirect();

        // 一覧を確認（HH:mm の表示を想定）
        $this->get('/attendance/list')
            ->assertOk()
            ->assertSee($now->format('H:i'), false); // 09:12
    }
}
