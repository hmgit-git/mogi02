<?php

namespace Tests\Feature\Attendance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Attendance;

class PunchOutTest extends TestCase
{
    use RefreshDatabase;

    /** 勤務中の勤怠を用意（当日9:00出勤済み・退勤前） */
    private function seedWorking(User $user, Carbon $nowJst): Attendance
    {
        return Attendance::query()->create([
            'user_id'     => $user->id,
            'work_date'   => $nowJst->toDateString(),
            'clock_in_at' => $nowJst->copy()->setTime(9, 0, 0), // 09:00 出勤
            // clock_out_at なし → 勤務中
        ]);
    }

    /** @test */
    public function 退勤ボタンが正しく機能する()
    {
        $now = Carbon::create(2025, 9, 1, 10, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $this->seedWorking($user, $now);

        // 勤務中画面に「退勤」ボタンが見える
        $this->get('/attendance')->assertOk()->assertSee('退勤', false);

        // 18:15 に退勤
        Carbon::setTestNow($now->copy()->setTime(18, 15, 0));
        $this->post('/attendance/out')->assertRedirect();

        // ステータスが「退勤済」になる（表示文言に合わせて）
        $this->get('/attendance')->assertOk()->assertSee('退勤済', false);
    }

    /** @test */
    public function 退勤時刻が勤怠一覧画面で確認できる()
    {
        $now = Carbon::create(2025, 9, 1, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        // 勤務外 → 出勤 → 退勤 の順で操作
        $this->post('/attendance/in')->assertRedirect();

        Carbon::setTestNow($now->copy()->setTime(18, 15, 0));
        $this->post('/attendance/out')->assertRedirect();

        // 勤怠一覧で退勤時刻が表示されている（HH:MM）
        $this->get('/attendance/list')
            ->assertOk()
            ->assertSee('18:15', false);
    }
}
