<?php

namespace Tests\Feature\Attendance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Attendance;

class AttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    private function loginVerifiedUser(): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);
        return $user;
    }

    private function findAttendance(User $user, string $ymd): ?Attendance
    {
        return Attendance::where('user_id', $user->id)
            ->where('work_date', $ymd)
            ->first();
    }

    /** @test */
    public function 勤怠詳細_名前がログインユーザーの氏名になっている()
    {
        Carbon::setTestNow(Carbon::create(2025, 9, 1, 9, 0, 0, 'Asia/Tokyo'));
        $user = $this->loginVerifiedUser();

        // 出勤 → その日の勤怠レコードを作る
        $this->post('/attendance/in')->assertRedirect();

        $att = $this->findAttendance($user, '2025-09-01');
        $this->assertNotNull($att, '勤怠レコードが作成されていません');

        // 詳細へ
        $res = $this->get(route('attendance.detail', ['id' => $att->id]))->assertOk();

        // 画面にユーザー名が出ていること
        $res->assertSee($user->name, false);
    }

    /** @test */
    public function 勤怠詳細_日付が選択した日付になっている()
    {
        Carbon::setTestNow(Carbon::create(2025, 9, 1, 9, 0, 0, 'Asia/Tokyo'));
        $user = $this->loginVerifiedUser();

        $this->post('/attendance/in')->assertRedirect();
        $att = $this->findAttendance($user, '2025-09-01');
        $this->assertNotNull($att);

        $res  = $this->get(route('attendance.detail', ['id' => $att->id]))->assertOk();
        $html = $res->getContent();

        // 表記ゆれに強い判定（YYYY/MM/DD or YYYY-MM-DD or YYYY年M月D日）
        $this->assertTrue(
            (bool)preg_match('/\b2025[\/-]09[\/-]01\b/u', $html) ||
                (bool)preg_match('/2025年\s*9月\s*1日/u', $html),
            '詳細画面に 2025/09/01（または同等表記）が見つかりませんでした'
        );
    }

    /** @test */
    public function 勤怠詳細_出勤退勤が打刻と一致している()
    {
        $user = $this->loginVerifiedUser();

        // 09:00 出勤
        Carbon::setTestNow(Carbon::create(2025, 9, 1, 9, 0, 0, 'Asia/Tokyo'));
        $this->post('/attendance/in')->assertRedirect();

        // 18:30 退勤
        Carbon::setTestNow(Carbon::create(2025, 9, 1, 18, 30, 0, 'Asia/Tokyo'));
        $this->post('/attendance/out')->assertRedirect();

        $att = $this->findAttendance($user, '2025-09-01');
        $this->assertNotNull($att);

        $res = $this->get(route('attendance.detail', ['id' => $att->id]))->assertOk();

        // 出勤・退勤欄に打刻時刻が表示されていること
        $res->assertSee('09:00', false);
        $this->assertTrue(
            $res->getContent() && (str_contains($res->getContent(), '18:30')),
            '退勤時刻 18:30 が表示されていません'
        );
    }

    /** @test */
    public function 勤怠詳細_休憩欄が打刻と一致している()
    {
        $user = $this->loginVerifiedUser();

        // 出勤
        Carbon::setTestNow(Carbon::create(2025, 9, 1, 9, 0, 0, 'Asia/Tokyo'));
        $this->post('/attendance/in')->assertRedirect();

        // 12:15 休憩入
        Carbon::setTestNow(Carbon::create(2025, 9, 1, 12, 15, 0, 'Asia/Tokyo'));
        $this->post('/attendance/break/start')->assertRedirect();

        // 12:45 休憩戻（30分）
        Carbon::setTestNow(Carbon::create(2025, 9, 1, 12, 45, 0, 'Asia/Tokyo'));
        $this->post('/attendance/break/end')->assertRedirect();

        $att = $this->findAttendance($user, '2025-09-01');
        $this->assertNotNull($att);

        $res  = $this->get(route('attendance.detail', ['id' => $att->id]))->assertOk();
        $html = $res->getContent();

        // 休憩開始・終了の値が画面に出ていることを確認（要件：打刻と一致）
        $res->assertSee('12:15', false);
        $res->assertSee('12:45', false);

        // 同じ「休憩」ブロック内に 12:15 → 12:45 が並んでいることも軽く検証
        $this->assertMatchesRegularExpression(
            '/休憩.*?12:15.*?12:45/su',
            $html,
            '休憩欄に 12:15 と 12:45 の組み合わせが見当たりません'
        );
    }
}
