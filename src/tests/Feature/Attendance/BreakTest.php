<?php

namespace Tests\Feature\Attendance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Attendance;

class BreakTest extends TestCase
{
    use RefreshDatabase;

    // プロジェクトの表示に合わせて必要なら変更
    private string $LABEL_WORKING = '出勤中';
    private string $LABEL_BREAK   = '休憩中';

    /** 出勤中ユーザーを用意（当日勤怠あり・退勤前・休憩中ではない） */
    private function seedWorking(User $user, Carbon $nowJst): Attendance
    {
        return Attendance::query()->create([
            'user_id'     => $user->id,
            'work_date'   => $nowJst->toDateString(),
            'clock_in_at' => $nowJst->copy()->setTime(9, 0, 0), // 09:00 出勤済み
            // clock_out_at なし → 勤務中
        ]);
    }

    /** @test */
    public function 休憩入ボタンが正しく機能する()
    {
        $now = Carbon::create(2025, 9, 1, 10, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);
        $this->seedWorking($user, $now);

        // 休憩入ボタンが見える
        $this->get('/attendance')->assertOk()->assertSee('休憩入', false);

        // 12:00 に休憩開始
        Carbon::setTestNow($now->copy()->setTime(12, 0, 0));
        $this->post('/attendance/break/start')->assertRedirect();

        // ステータスが休憩中
        $this->get('/attendance')->assertOk()->assertSee($this->LABEL_BREAK, false);
    }

    /** @test */
    public function 休憩は一日に何回でもできる()
    {
        $now = Carbon::create(2025, 9, 1, 10, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);
        $this->seedWorking($user, $now);

        // 1 回目 休憩入→戻
        Carbon::setTestNow($now->copy()->setTime(12, 0, 0));
        $this->post('/attendance/break/start')->assertRedirect();
        Carbon::setTestNow($now->copy()->setTime(12, 30, 0));
        $this->post('/attendance/break/end')->assertRedirect();

        // 再び「休憩入」ボタンが表示される
        $this->get('/attendance')->assertOk()->assertSee('休憩入', false);
    }

    /** @test */
    public function 休憩戻ボタンが正しく機能する()
    {
        $now = Carbon::create(2025, 9, 1, 10, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);
        $this->seedWorking($user, $now);

        // 休憩入
        Carbon::setTestNow($now->copy()->setTime(12, 0, 0));
        $this->post('/attendance/break/start')->assertRedirect();

        // 休憩戻ボタンが見える
        $this->get('/attendance')->assertOk()->assertSee('休憩戻', false);

        // 休憩戻 → ステータスが「出勤中」
        Carbon::setTestNow($now->copy()->setTime(12, 45, 0));
        $this->post('/attendance/break/end')->assertRedirect();

        $this->get('/attendance')->assertOk()->assertSee($this->LABEL_WORKING, false);
    }

    /** @test */
    public function 休憩戻は一日に何回でもできる()
    {
        $now = Carbon::create(2025, 9, 1, 10, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);
        $this->seedWorking($user, $now);

        // 1 回目：入→戻
        Carbon::setTestNow($now->copy()->setTime(12, 0, 0));
        $this->post('/attendance/break/start')->assertRedirect();
        Carbon::setTestNow($now->copy()->setTime(12, 30, 0));
        $this->post('/attendance/break/end')->assertRedirect();

        // 2 回目：入（→ この時点で「休憩戻」が表示されるはず）
        Carbon::setTestNow($now->copy()->setTime(15, 0, 0));
        $this->post('/attendance/break/start')->assertRedirect();

        $this->get('/attendance')->assertOk()->assertSee('休憩戻', false);
    }

    /** @test */
    public function 休憩時刻が勤怠一覧画面で確認できる()
    {
        $now = Carbon::create(2025, 9, 1, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);
        $this->seedWorking($user, $now);

        // 12:00 → 12:45 の 45 分休憩
        Carbon::setTestNow($now->copy()->setTime(12, 0, 0));
        $this->post('/attendance/break/start')->assertRedirect();
        Carbon::setTestNow($now->copy()->setTime(12, 45, 0));
        $this->post('/attendance/break/end')->assertRedirect();

        // 勤怠一覧の「休憩」列は「X:Y」表記（45分なら "0:45"）
        $this->get('/attendance/list')
            ->assertOk()
            ->assertSee('0:45', false);
    }
}
