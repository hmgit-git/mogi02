<?php

namespace Tests\Feature\Attendance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Attendance;

class AttendanceListTest extends TestCase
{
    use RefreshDatabase;

    private function seedAtt(User $user, string $ymd, ?string $in = null, ?string $out = null): Attendance
    {
        // 文字列は JST 前提で Carbon に
        $inAt  = $in  ? Carbon::createFromFormat('Y-m-d H:i', "$ymd $in", 'Asia/Tokyo')  : null;
        $outAt = $out ? Carbon::createFromFormat('Y-m-d H:i', "$ymd $out", 'Asia/Tokyo') : null;

        return Attendance::query()->create([
            'user_id'     => $user->id,
            'work_date'   => $ymd,
            'clock_in_at' => $inAt,
            'clock_out_at' => $outAt,
        ]);
    }

    /** @test */
    public function 自分の勤怠情報が全て表示される()
    {
        // 2025-09 を対象月に
        Carbon::setTestNow(Carbon::create(2025, 9, 15, 12, 0, 0, 'Asia/Tokyo'));

        $me   = User::factory()->create(['email_verified_at' => now()]);
        $you  = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($me);

        // 自分の勤怠（9/01 と 9/10）
        $this->seedAtt($me,  '2025-09-01', '07:11', '13:22');
        $this->seedAtt($me,  '2025-09-10', '09:00', '18:00');

        // 他人の勤怠（同月・紛らわしい時刻）
        $this->seedAtt($you, '2025-09-01', '08:08', '19:19');

        // 一覧表示（デフォは当月）
        $res = $this->get('/attendance/list')->assertOk();

        // 自分の記録は見える
        $res->assertSee('07:11', false)
            ->assertSee('13:22', false)
            ->assertSee('09:00', false)
            ->assertSee('18:00', false);

        // 他人の時刻は出ない
        $res->assertDontSee('08:08', false)
            ->assertDontSee('19:19', false);
    }

    /** @test */
    public function 一覧遷移時は現在の月が表示される()
    {
        Carbon::setTestNow(Carbon::create(2025, 9, 1, 9, 0, 0, 'Asia/Tokyo'));

        $me = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($me);

        // 月ピッカーの value（YYYY-MM）で検証（ラベル表記の揺れ対策）
        $this->get('/attendance/list')
            ->assertOk()
            ->assertSee('value="2025-09"', false);
    }

    /** @test */
    public function 前月ボタンで前月の情報が表示される()
    {
        Carbon::setTestNow(Carbon::create(2025, 9, 10, 12, 0, 0, 'Asia/Tokyo'));

        $me = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($me);

        // 前月（2025-08）にユニークな時刻を仕込む
        $this->seedAtt($me, '2025-08-05', '06:07', '08:09');

        // 「前月」相当の URL に直接アクセス（UI押下相当）
        $this->get('/attendance/list?month=2025-08')
            ->assertOk()
            ->assertSee('06:07', false)
            ->assertSee('08:09', false)
            // 月ピッカー value が 2025-08 になっていることも確認
            ->assertSee('value="2025-08"', false);
    }

    /** @test */
    public function 翌月ボタンで翌月の情報が表示される()
    {
        Carbon::setTestNow(Carbon::create(2025, 9, 10, 12, 0, 0, 'Asia/Tokyo'));

        $me = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($me);

        // 翌月（2025-10）
        $this->seedAtt($me, '2025-10-03', '10:11', '12:13');

        $this->get('/attendance/list?month=2025-10')
            ->assertOk()
            ->assertSee('10:11', false)
            ->assertSee('12:13', false)
            ->assertSee('value="2025-10"', false);
    }

    /** @test */
    public function 詳細ボタンでその日の勤怠詳細へ遷移できる()
    {
        Carbon::setTestNow(Carbon::create(2025, 9, 1, 9, 0, 0, 'Asia/Tokyo'));

        $me = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($me);

        $att = $this->seedAtt($me, '2025-09-01', '09:30', '18:45');

        // 一覧に詳細リンク（/attendance/detail/{id}）が出ている
        $list = $this->get('/attendance/list')->assertOk();
        $detailUrl = route('attendance.detail', ['id' => $att->id]);

        $list->assertSee($detailUrl, false);

        // 実際に詳細へアクセスして 200 が返ること
        $this->get($detailUrl)->assertOk();
    }
}
