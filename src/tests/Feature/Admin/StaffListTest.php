<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;


class StaffListTest extends TestCase
{
    use RefreshDatabase;

    private string $tz = 'Asia/Tokyo';

    private const STAFF_ATT_LIST_ROUTE = 'admin.staff.attendances';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureEmailIsVerified::class);
    }

    private function makeAdmin(array $overrides = []): User
    {
        return $this->makeUser(array_merge([
            'name'     => '管理者',
            'email'    => 'admin@example.com',
            'is_admin' => true,
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

    private function staffIndexUrl(): string
    {
        try {
            return route('admin.staff.index');
        } catch (\Throwable $e) {
            return '/admin/staff';
        }
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

    /** @test 管理者：①全一般ユーザーの氏名・メールが一覧で見える */
    public function 管理者は全一般ユーザーの氏名とメールアドレスを確認できる(): void
    {
        $admin = $this->makeAdmin();

        $u1 = $this->makeUser(['name' => '田中 太郎', 'email' => 't1@example.com']);
        $u2 = $this->makeUser(['name' => '佐藤 花子', 'email' => 't2@example.com']);
        $u3 = $this->makeUser(['name' => '鈴木 三郎', 'email' => 't3@example.com']);
        $this->makeAdmin(['name' => '管理者2', 'email' => 'admin2@example.com']);

        $res = $this->actingAs($admin, 'admin')
            ->get($this->staffIndexUrl())
            ->assertOk();

        $res->assertSee('田中 太郎', false)->assertSee('t1@example.com', false)
            ->assertSee('佐藤 花子', false)->assertSee('t2@example.com', false)
            ->assertSee('鈴木 三郎', false)->assertSee('t3@example.com', false);
    }

    /** @test 管理者：②選択したユーザーの勤怠情報が正しく表示される */
    public function 管理者は選択ユーザーの勤怠一覧を正しく確認できる(): void
    {
        $admin  = $this->makeAdmin();
        $target = $this->makeUser(['name' => '対象 太郎', 'email' => 'taishou@example.com']);
        $other  = $this->makeUser(['name' => '他人 次郎', 'email' => 'other@example.com']);

        $this->makeAttendance($target, '2025-09-01', '09:00', '18:00');
        $this->makeAttendance($target, '2025-09-02', '10:15', '19:00');
        $this->makeAttendance($other,  '2025-09-02', '08:30', '17:30');

        $res = $this->actingAs($admin, 'admin')
            ->get(route(self::STAFF_ATT_LIST_ROUTE, ['user' => $target->id, 'month' => '2025-09']))
            ->assertOk();

        $res->assertSee('対象 太郎', false)
            ->assertSee('09/01', false)->assertSee('09:00', false)->assertSee('18:00', false)
            ->assertSee('09/02', false)->assertSee('10:15', false)->assertSee('19:00', false)
            ->assertDontSee('他人 次郎', false)
            ->assertDontSee('08:30', false)->assertDontSee('17:30', false);
    }
    // 追加：月ラベル "YYYY/MM" を拾うヘルパ
    private function assertShowsMonth(TestResponse $res, string $ym): void
    {
        $res->assertSee(str_replace('-', '/', $ym), false); // "2025-08" → "2025/08"
    }

    /** @test 管理者：③「前月」押下で前月の勤怠が表示される */
    public function 管理者はスタッフ勤怠一覧で前月を表示できる(): void
    {
        $admin = $this->makeAdmin();
        $u     = $this->makeUser(['name' => '対象 太郎', 'email' => 'taishou@example.com']);

        // 前月のデータ（表示される想定）
        $this->makeAttendance($u, '2025-08-01', '09:00', '18:00');
        $this->makeAttendance($u, '2025-08-15', '10:00', '19:00');

        // 当月のノイズ（前月画面には混ざらない想定）
        $this->makeAttendance($u, '2025-09-02', '11:11', '22:22');

        // 「前月」を押したのと同じ＝month=2025-08 でアクセス
        $res = $this->actingAs($admin, 'admin')
            ->get(route(self::STAFF_ATT_LIST_ROUTE, [
                'user'  => $u->id,
                'month' => '2025-08',
            ]))
            ->assertOk();

        // ヘッダの月ラベル（例: 2025/08）
        $this->assertShowsMonth($res, '2025-08');

        // 前月データが出る（曜日は無視して "MM/DD" 部分と時刻を確認）
        $res->assertSee('08/01', false)->assertSee('09:00', false)->assertSee('18:00', false)
            ->assertSee('08/15', false)->assertSee('10:00', false)->assertSee('19:00', false)

            // 当月のレコードは混ざらないことの確認
            ->assertDontSee('09/02', false)
            ->assertDontSee('11:11', false)
            ->assertDontSee('22:22', false);
    }

    /** @test 管理者：④「翌月」押下で翌月の勤怠が表示される */
    public function 管理者はスタッフ勤怠一覧で翌月を表示できる(): void
    {
        $admin = $this->makeAdmin();
        $u     = $this->makeUser(['name' => '対象 太郎', 'email' => 'taishou@example.com']);

        // 翌月のデータ（表示される想定）
        $this->makeAttendance($u, '2025-10-01', '09:00', '18:00');
        $this->makeAttendance($u, '2025-10-15', '10:00', '19:00');

        // 当月のノイズ（翌月画面には混ざらない想定）
        $this->makeAttendance($u, '2025-09-02', '11:11', '22:22');

        // 「翌月」を押したのと同じ＝month=2025-10 でアクセス
        $res = $this->actingAs($admin, 'admin')
            ->get(route(self::STAFF_ATT_LIST_ROUTE, [
                'user'  => $u->id,
                'month' => '2025-10',
            ]))
            ->assertOk();

        // ヘッダの月ラベル（例: 2025/10）
        $this->assertShowsMonth($res, '2025-10');

        // 翌月データが出る（曜日は無視して "MM/DD" と時刻を確認）
        $res->assertSee('10/01', false)->assertSee('09:00', false)->assertSee('18:00', false)
            ->assertSee('10/15', false)->assertSee('10:00', false)->assertSee('19:00', false)

            // 当月のレコードは混ざらないことの確認
            ->assertDontSee('09/02', false)
            ->assertDontSee('11:11', false)
            ->assertDontSee('22:22', false);
    }

    /** @test 管理者：⑤「詳細」押下でその日の勤怠詳細に遷移できる */
    public function 管理者はスタッフ勤怠一覧から詳細リンクで勤怠詳細へ遷移できる(): void
    {
        $admin  = $this->makeAdmin();
        $target = $this->makeUser(['name' => '対象 太郎', 'email' => 'taishou@example.com']);

        // 当月データ1件（詳細に飛ぶ対象）
        $att = $this->makeAttendance($target, '2025-09-02', '09:00', '18:00');

        // 一覧ページ（当月）
        $listRes = $this->actingAs($admin, 'admin')
            ->get(route(self::STAFF_ATT_LIST_ROUTE, [
                'user'  => $target->id,
                'month' => '2025-09',
            ]))
            ->assertOk();

        // 一覧に「詳細」リンクが出ていて、かつリンク先が該当勤怠の詳細であること
        $detailUrl = route('admin.attendances.show', $att->id);
        $listRes->assertSee('詳細', false)
            ->assertSee($detailUrl, false);

        // 実際に詳細ページへアクセスして内容を確認
        $detailRes = $this->actingAs($admin, 'admin')
            ->get($detailUrl)
            ->assertOk();

        $detailRes->assertSee('勤怠詳細', false)
            ->assertSee('対象 太郎', false)
            ->assertSee('09:00', false)
            ->assertSee('18:00', false);
    }
}