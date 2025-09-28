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

class AttendanceListTest extends TestCase
{
    use RefreshDatabase;

    private string $tz = 'Asia/Tokyo';

    // ルート“名”を使う（将来のURL変更に強い）
    private const ADMIN_LIST_ROUTE = 'admin.attendance.list.pg08';

    protected function setUp(): void
    {
        parent::setUp();
        // メール確認のミドルウェアは無効化（ログイン自体は残す）
        $this->withoutMiddleware(EnsureEmailIsVerified::class);
        Carbon::setTestNow(Carbon::parse('2025-09-02 09:00', $this->tz));
    }

    /** 管理者ユーザーを作る（実装に合わせて is_admin 等で判定している想定） */
    private function makeAdmin(array $overrides = []): User
    {
        return $this->makeUser(array_merge([
            'name'     => '管理者',
            'email'    => 'admin@example.com',
            'is_admin' => true, // ← プロジェクトの判定に合わせて
        ], $overrides));
    }

    /** 一般ユーザー作成 */
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

    /** 勤怠レコード作成 */
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

    /** 日付表示を柔軟にパスさせる（和暦/ハイフン/スラッシュ/日付のみ） */
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
    
    protected function tearDown(): void
    {
        \Illuminate\Support\Carbon::setTestNow(null); // リセット
        parent::tearDown();
    }


    /** @test 管理者：① その日になされた全ユーザーの勤怠情報が正確に確認できる */
    public function 管理者は指定日の全ユーザー勤怠を確認できる(): void
    {
        $admin = $this->makeAdmin();
        $u1    = $this->makeUser(['name' => '田中 太郎', 'email' => 't1@example.com']);
        $u2    = $this->makeUser(['name' => '佐藤 花子', 'email' => 't2@example.com']);

        $this->makeAttendance($u1, '2025-09-02', '09:00', '18:00');
        $this->makeAttendance($u2, '2025-09-02', '10:00', '19:30');
        $this->makeAttendance($u1, '2025-09-01', '09:00', '18:00'); // ノイズ

        $res = $this->actingAs($admin, 'admin')
            ->get(route(self::ADMIN_LIST_ROUTE, ['date' => '2025-09-02']))
            ->assertOk(); // ← dumpを消してこれを復活

        $this->assertShowsDateFlexible($res, '2025-09-02');

        $res->assertSee('田中 太郎', false)->assertSee('09:00', false)->assertSee('18:00', false)
            ->assertSee('佐藤 花子', false)->assertSee('10:00', false)->assertSee('19:30', false);
    }

    // 追加：日次一覧のルート名
    private const ADMIN_DAILY_ROUTE = 'admin.attendances.daily';

    /** @test 管理者：② 遷移した際に現在の日付が表示される（日次） */
    public function 管理者日次一覧は日付未指定で当日を表示する(): void
    {
        $admin = $this->makeAdmin();

        $res = $this->actingAs($admin, 'admin')
            ->get(route(self::ADMIN_DAILY_ROUTE)) // date指定なし → 当日
            ->assertOk();

        // 見出し「2025年9月2日の勤怠」や <strong>2025/09/02</strong> 等を柔軟に検出
        $this->assertShowsDateFlexible($res, '2025-09-02');
    }

    /** @test 管理者：③ 「前日」を押下した時に前日の勤怠情報が表示される（日次） */
    public function 管理者日次一覧は前日を表示できる(): void
    {
        $admin = $this->makeAdmin();
        $u     = $this->makeUser(['name' => '前日 太郎', 'email' => 'prev@example.com']);

        // 前日の打刻データ
        $this->makeAttendance($u, '2025-09-01', '08:30', '17:15');

        $res = $this->actingAs($admin, 'admin')
            ->get(route(self::ADMIN_DAILY_ROUTE, ['date' => '2025-09-01']))
            ->assertOk();

        $this->assertShowsDateFlexible($res, '2025-09-01');

        // テーブルに内容が出ていること
        $res->assertSee('前日 太郎', false)
            ->assertSee('08:30', false)
            ->assertSee('17:15', false);

        // （任意）前日/翌日のナビゲーションが適切な href を持つか軽く確認
        $res->assertSee('href="http://localhost/admin/attendances?date=2025-08-31"', false)
            ->assertSee('href="http://localhost/admin/attendances?date=2025-09-02"', false);
    }

    /** @test 管理者：④ 「翌日」を押下した時に翌日の勤怠情報が表示される（日次） */
    public function 管理者日次一覧は翌日を表示できる(): void
    {
        $admin = $this->makeAdmin();
        $u     = $this->makeUser(['name' => '翌日 花子', 'email' => 'next@example.com']);

        // 翌日の打刻データ
        $this->makeAttendance($u, '2025-09-03', '11:00', '20:00');

        $res = $this->actingAs($admin, 'admin')
            ->get(route(self::ADMIN_DAILY_ROUTE, ['date' => '2025-09-03']))
            ->assertOk();

        $this->assertShowsDateFlexible($res, '2025-09-03');

        $res->assertSee('翌日 花子', false)
            ->assertSee('11:00', false)
            ->assertSee('20:00', false);

    }
}
