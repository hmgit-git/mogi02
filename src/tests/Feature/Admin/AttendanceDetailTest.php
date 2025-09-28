<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;

class AttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    private string $tz = 'Asia/Tokyo';

    // ルート名（route:list の name に合わせる）
    private const ADMIN_DETAIL_ROUTE = 'admin.attendances.show';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureEmailIsVerified::class);
        Carbon::setTestNow(Carbon::parse('2025-09-02 09:00', $this->tz));
    }

    private function makeAdmin(array $overrides = []): User
    {
        return $this->makeUser(array_merge([
            'name'     => '管理者',
            'email'    => 'admin@example.com',
            'is_admin' => true, // プロジェクトの判定に合わせて
        ], $overrides));
    }

    private function makeUser(array $overrides = []): User
    {
        $user = User::create(array_merge([
            'name'     => '一般 太郎',
            'email'    => 'taro@example.com',
            'password' => Hash::make('password123'),
        ], $overrides));

        $user->forceFill(['email_verified_at' => now()])->save();
        return $user->refresh();
    }

    private function makeAttendance(User $user, string $date, ?string $in, ?string $out): Attendance
    {
        $att = new Attendance();
        $att->user_id      = $user->id;
        $att->work_date    = $date;
        $att->clock_in_at  = $in  ? Carbon::parse("$date $in",  $this->tz) : null;
        $att->clock_out_at = $out ? Carbon::parse("$date $out", $this->tz) : null;
        $att->save();
        return $att->refresh();
    }

    private function assertShowsDateFlexible(TestResponse $res, string $ymd): void
    {
        [$y, $m, $d] = explode('-', $ymd);
        $m2 = sprintf('%02d', (int)$m);
        $d2 = sprintf('%02d', (int)$d);

        $html = $res->getContent();
        $ok =
            (str_contains($html, "{$y}年") && str_contains($html, ((int)$m) . "月" . ((int)$d) . "日")) ||
            str_contains($html, "{$y}-{$m2}-{$d2}") ||
            str_contains($html, "{$y}/{$m2}/{$d2}") ||
            str_contains($html, "{$m2}/{$d2}");

        $this->assertTrue($ok, "Date {$ymd} not shown in a known format.");
    }

    /** @test 管理者：勤怠詳細① 選択した勤怠の詳細が表示される */
    public function 管理者は勤怠詳細で選択したレコード内容を確認できる(): void
    {
        $admin = $this->makeAdmin();

        $u1 = $this->makeUser(['name' => '詳細 太郎', 'email' => 'detail@example.com']);
        $u2 = $this->makeUser(['name' => '別ユーザー', 'email' => 'other@example.com']);

        $target = $this->makeAttendance($u1, '2025-09-05', '08:45', '17:10');
        $this->makeAttendance($u2, '2025-09-05', '09:00', '18:00'); // ノイズ

        // 管理者ガードでアクセス（アプリが admin ガードを使っている前提）
        $res = $this->actingAs($admin, 'admin')
            ->get(route(self::ADMIN_DETAIL_ROUTE, ['id' => $target->id]))
            ->assertOk();

        $this->assertShowsDateFlexible($res, '2025-09-05');

        $res->assertSee('詳細 太郎', false)
            ->assertSee('08:45', false)
            ->assertSee('17:10', false)
            ->assertDontSee('別ユーザー', false)
            ->assertDontSee('09:00', false)
            ->assertDontSee('18:00', false);
    }
    /** @test 管理者：② 出勤 > 退勤ならエラーを表示する */
    public function 出勤時間が退勤時間より後になっている場合はエラーメッセージが表示される(): void
    {
        $admin = $this->makeAdmin();

        $user = $this->makeUser(['name' => '対象 太郎', 'email' => 'clockin-after@example.com']);
        $att  = $this->makeAttendance($user, '2025-09-02', '09:00', '20:00');

        $res = $this->from(route('admin.attendances.show', $att->id))
            ->actingAs($admin, 'admin')
            ->put(route('admin.attendances.update', $att->id), [
                'date'         => '2025-09-02',
                'clock_in_at'  => '21:00', // 退勤より後
                'clock_out_at' => '20:00',
                'breaks'       => [],
                'reason'       => '検証',
            ]);

        $res->assertRedirect(route('admin.attendances.show', $att->id));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.attendances.show', $att->id))
            ->assertSee('出勤時間もしくは退勤時間が不適切な値です', false);
    }

    /** @test 管理者：③ 休憩開始 > 退勤ならエラーを表示する */
    public function 休憩開始時間が退勤時間より後になっている場合はエラーメッセージが表示される(): void
    {
        $admin = $this->makeAdmin();

        $user = $this->makeUser(['name' => '対象 花子', 'email' => 'breakstart-after@example.com']);
        $att  = $this->makeAttendance($user, '2025-09-02', '09:00', '20:00');

        $res = $this->from(route('admin.attendances.show', $att->id))
            ->actingAs($admin, 'admin')
            ->put(route('admin.attendances.update', $att->id), [
                'date'         => '2025-09-02',
                'clock_in_at'  => '09:00',
                'clock_out_at' => '20:00',
                'breaks'       => [
                    ['start_at' => '21:00', 'end_at' => ''], // endは空にして別エラー（終了>退勤）を避ける
                ],
                'reason'       => '検証',
            ]);

        $res->assertRedirect(route('admin.attendances.show', $att->id));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.attendances.show', $att->id))
            ->assertSee('休憩時間が不適切な値です', false);
    }
    /** @test 管理者：④ 休憩終了 > 退勤ならエラーを表示する */
    public function 休憩終了時間が退勤時間より後になっている場合はエラーメッセージが表示される(): void
    {
        $admin = $this->makeAdmin();

        $user = $this->makeUser(['name' => '対象 四郎', 'email' => 'breakend-after@example.com']);
        $att  = $this->makeAttendance($user, '2025-09-02', '09:00', '20:00');

        $res = $this->from(route('admin.attendances.show', $att->id))
            ->actingAs($admin, 'admin')
            ->put(route('admin.attendances.update', $att->id), [
                'date'         => '2025-09-02',
                'clock_in_at'  => '09:00',
                'clock_out_at' => '20:00',
                'breaks'       => [
                    ['start_at' => '19:30', 'end_at' => '21:00'], // 退勤(20:00)より後に終了
                ],
                'reason'       => '検証',
            ]);

        $res->assertRedirect(route('admin.attendances.show', $att->id));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.attendances.show', $att->id))
            ->assertSee('休憩時間もしくは退勤時間が不適切な値です', false);
    }


    /** @test 管理者：⑤ 備考未入力ならエラーを表示する */
    public function 備考欄が未入力の場合はエラーメッセージが表示される(): void
    {
        $admin = $this->makeAdmin();

        $user = $this->makeUser(['name' => '対象 三郎', 'email' => 'reason-empty@example.com']);
        $att  = $this->makeAttendance($user, '2025-09-02', '09:00', '18:00');

        $res = $this->from(route('admin.attendances.show', $att->id))
            ->actingAs($admin, 'admin')
            ->put(route('admin.attendances.update', $att->id), [
                'date'         => '2025-09-02',
                'clock_in_at'  => '09:00',
                'clock_out_at' => '18:00',
                'breaks'       => [
                    ['start_at' => '12:00', 'end_at' => '12:30'],
                ],
                'reason'       => '', // 未入力
            ]);

        $res->assertRedirect(route('admin.attendances.show', $att->id));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.attendances.show', $att->id))
            ->assertSee('備考を記入してください', false);
    }
}
