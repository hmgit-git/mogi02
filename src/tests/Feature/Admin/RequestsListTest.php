<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceEditRequest;
use Illuminate\Support\Carbon;

class RequestsListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // メール確認ミドルウェアは無効化（管理画面アクセス簡略化）
        $this->withoutMiddleware(EnsureEmailIsVerified::class);
    }

    /** 管理者ユーザー作成 */
    private function makeAdmin(array $overrides = []): User
    {
        return $this->makeUser(array_merge([
            'name'     => '管理者',
            'email'    => 'admin@example.com',
            'is_admin' => true,
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

    /** 勤怠作成（リクエスト紐付け用に最低限） */
    private function makeAttendance(User $user, string $ymd = '2025-09-02'): Attendance
    {
        return Attendance::create([
            'user_id'      => $user->id,
            'work_date'    => $ymd,
            'clock_in_at'  => null,
            'clock_out_at' => null,
        ])->refresh();
    }

    /** ルートURL（名前付きがあれば優先） */
    private function adminRequestsUrl(string $status = 'pending'): string
    {
        try {
            return route('admin.requests.index', ['status' => $status]);
        } catch (\Throwable $e) {
            return "/admin/requests?status={$status}";
        }
    }

    /** @test 管理者：①承認待ちの修正申請が全て表示される */
    public function 管理者は承認待ちの修正申請を一覧で確認できる(): void
    {
        $admin = $this->makeAdmin();

        // 申請者（一般ユーザー）を複数用意
        $u1 = $this->makeUser(['name' => '田中 太郎', 'email' => 't1@example.com']);
        $u2 = $this->makeUser(['name' => '佐藤 花子', 'email' => 't2@example.com']);
        $u3 = $this->makeUser(['name' => '鈴木 三郎', 'email' => 't3@example.com']);

        // 勤怠
        $a1 = $this->makeAttendance($u1);
        $a2 = $this->makeAttendance($u2);
        $a3 = $this->makeAttendance($u3);

        // 承認待ち（表示される想定）
        AttendanceEditRequest::create([
            'applicant_id'      => $u1->id,
            'attendance_id'     => $a1->id,
            'status'            => 'pending',
            'reason'            => 'Aの理由',
            'requested_changes' => [],
        ]);
        AttendanceEditRequest::create([
            'applicant_id'      => $u2->id,
            'attendance_id'     => $a2->id,
            'status'            => 'pending',
            'reason'            => 'Bの理由',
            'requested_changes' => [],
        ]);

        // 承認済/却下（承認待ちタブには出ない想定）
        AttendanceEditRequest::create([
            'applicant_id'      => $u3->id,
            'attendance_id'     => $a3->id,
            'status'            => 'approved',
            'reason'            => '承認済みの理由',
            'requested_changes' => [],
        ]);
        AttendanceEditRequest::create([
            'applicant_id'      => $u3->id,
            'attendance_id'     => $a3->id,
            'status'            => 'rejected',
            'reason'            => '却下の理由',
            'requested_changes' => [],
        ]);

        // アクセス：承認待ちタブ
        $res = $this->actingAs($admin, 'admin')
            ->get($this->adminRequestsUrl('pending'))
            ->assertOk();

        // 承認待ちが見える
        $res->assertSee('田中 太郎', false)->assertSee('Aの理由', false)
            ->assertSee('佐藤 花子', false)->assertSee('Bの理由', false);

        // 承認済/却下は見えない
        $res->assertDontSee('承認済みの理由', false)
            ->assertDontSee('却下の理由', false)
            ->assertDontSee('鈴木 三郎', false);
    }
    /** @test 管理者：②承認済みの修正申請が全て表示される */
    public function 管理者は承認済みの修正申請を一覧で確認できる(): void
    {
        $admin = $this->makeAdmin();

        // 申請者（一般ユーザー）
        $u1 = $this->makeUser(['name' => '田中 太郎', 'email' => 't1@example.com']);
        $u2 = $this->makeUser(['name' => '佐藤 花子', 'email' => 't2@example.com']);
        $u3 = $this->makeUser(['name' => '鈴木 三郎', 'email' => 't3@example.com']);

        // 勤怠
        $a1 = $this->makeAttendance($u1);
        $a2 = $this->makeAttendance($u2);
        $a3 = $this->makeAttendance($u3);

        // 承認済み（表示される想定）
        \App\Models\AttendanceEditRequest::create([
            'applicant_id'      => $u1->id,
            'attendance_id'     => $a1->id,
            'status'            => 'approved',
            'reason'            => '承認Aの理由',
            'requested_changes' => [],
            'reviewed_at'       => now(),
        ]);
        \App\Models\AttendanceEditRequest::create([
            'applicant_id'      => $u2->id,
            'attendance_id'     => $a2->id,
            'status'            => 'approved',
            'reason'            => '承認Bの理由',
            'requested_changes' => [],
            'reviewed_at'       => now(),
        ]);

        // ノイズ：承認待ち/却下（承認済みタブには出ない）
        \App\Models\AttendanceEditRequest::create([
            'applicant_id'      => $u3->id,
            'attendance_id'     => $a3->id,
            'status'            => 'pending',
            'reason'            => '未承認の理由',
            'requested_changes' => [],
        ]);
        \App\Models\AttendanceEditRequest::create([
            'applicant_id'      => $u3->id,
            'attendance_id'     => $a3->id,
            'status'            => 'rejected',
            'reason'            => '却下の理由',
            'requested_changes' => [],
        ]);

        // 承認済みタブへ
        $res = $this->actingAs($admin, 'admin')
            ->get($this->adminRequestsUrl('approved'))
            ->assertOk();

        // 承認済みが見える
        $res->assertSee('田中 太郎', false)->assertSee('承認Aの理由', false)
            ->assertSee('佐藤 花子', false)->assertSee('承認Bの理由', false);

        // 未承認/却下は見えない
        $res->assertDontSee('未承認の理由', false)
            ->assertDontSee('却下の理由', false)
            ->assertDontSee('鈴木 三郎', false);
    }
    private function adminRequestShowUrl(int $id): string
    {
        try {
            return route('admin.requests.show', $id);
        } catch (\Throwable $e) {
            return "/admin/requests/{$id}";
        }
    }

    /** @test 管理者：③ 修正申請の詳細内容が正しく表示される */
    public function 管理者は修正申請の詳細内容を確認できる(): void
    {
        $admin     = $this->makeAdmin();
        $applicant = $this->makeUser(['name' => '申請 太郎', 'email' => 'apply@example.com']);

        // 元の勤怠
        $att = $this->makeAttendance($applicant, '2025-09-02', '09:00', '18:00');

        // 申請（修正内容つき）
        $req = AttendanceEditRequest::create([
            'applicant_id'      => $applicant->id,
            'attendance_id'     => $att->id,
            'status'            => 'pending',
            'reason'            => '退勤押し忘れのため修正',
            'requested_changes' => [
                'clock_in_at'  => '2025-09-02 09:05:00',
                'clock_out_at' => '2025-09-02 18:10:00',
                'breaks'       => [
                    ['start_at' => '2025-09-02 12:00:00', 'end_at' => '2025-09-02 12:30:00'],
                ],
            ],
        ]);

        // 詳細ページへ
        $res = $this->actingAs($admin, 'admin')
            ->get($this->adminRequestShowUrl($req->id))
            ->assertOk();

        // 申請者・理由
        $res->assertSee('申請 太郎', false)
            ->assertSee('退勤押し忘れのため修正', false);

        // 修正内容（分表示：09:05, 18:10, 12:00, 12:30）
        $res->assertSee('09:05', false)
            ->assertSee('18:10', false)
            ->assertSee('12:00', false)
            ->assertSee('12:30', false);

        // 対象日もどれかの表記で見えていること（柔らかめにチェック）
        $html = $res->getContent();
        $this->assertTrue(
            str_contains($html, '2025/09/02') ||
                str_contains($html, '2025-09-02') ||
                str_contains($html, '2025年') && str_contains($html, '9月2日'),
            '対象日が表示されていません。'
        );
    }
    private function adminRequestApproveUrl(AttendanceEditRequest $req): string
    {
        try {
            // プロジェクト側で定義済みならこちらを優先（例: admin.requests.approve）
            return route('admin.requests.approve', $req->id);
        } catch (\Throwable $e) {
            // 無ければ慣例的なURLにフォールバック
            return "/admin/requests/{$req->id}/approve";
        }
    }

    /** @test 管理者：④ 修正申請を承認すると勤怠が更新される */
    public function 管理者は修正申請の承認処理を実行でき勤怠が更新される(): void
    {
        $admin = $this->makeAdmin();
        $user  = $this->makeUser(['name' => '対象 太郎', 'email' => 'user@example.com']);

        // 元の勤怠（承認でこの値が書き換わる想定）
        $att = new Attendance();
        $att->user_id      = $user->id;
        $att->work_date    = '2025-09-02';
        $att->clock_in_at  = Carbon::parse('2025-09-02 09:00', 'Asia/Tokyo');
        $att->clock_out_at = Carbon::parse('2025-09-02 18:00', 'Asia/Tokyo');
        $att->save();
        $att->refresh();

        // 申請（差分を requested_changes に入れる。breaks は省略でもOK）
        $req = AttendanceEditRequest::create([
            'applicant_id'      => $user->id,
            'attendance_id'     => $att->id,
            'status'            => 'pending',
            'reason'            => '退勤押し忘れのため修正',
            'requested_changes' => [
                'clock_in_at'  => '2025-09-02 09:05:00',
                'clock_out_at' => '2025-09-02 18:10:00',
                'breaks' => [
                ['start_at' => '2025-09-02 12:00:00', 'end_at' => '2025-09-02 12:30:00'],
                ],
            ],
        ]);

        // 承認実行
        $this->actingAs($admin, 'admin')
            ->post($this->adminRequestApproveUrl($req), [])
            ->assertRedirect();

        // リフレッシュしてDB反映を検証
        $req->refresh();
        $att->refresh();

        // 申請ステータスが approved、レビュー日時が入っていること
        $this->assertSame('approved', $req->status);
        $this->assertNotNull($req->reviewed_at);

        // 勤怠が申請内容で更新されていること（時刻で確認）
        $this->assertSame('09:05', $att->clock_in_at->format('H:i'));
        $this->assertSame('18:10', $att->clock_out_at->format('H:i'));
        }
}
