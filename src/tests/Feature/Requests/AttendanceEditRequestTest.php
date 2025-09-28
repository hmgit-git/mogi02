<?php

namespace Tests\Feature\Requests;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceEditRequest;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;

class AttendanceEditRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // メール確認ミドルウェアは全テストで無効化
        $this->withoutMiddleware(EnsureEmailIsVerified::class);
    }

    private string $tz = 'Asia/Tokyo';

    /** ユーザー生成 */
    private function makeUser(array $overrides = []): User
    {
        $user = User::create(array_merge([
            'name' => '一般 太郎',
            'email' => 'taro@example.com',
            'password' => Hash::make('password123'),
        ], $overrides));

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user->refresh();
    }

    /** 勤怠レコード生成 */
    private function makeAttendance(User $user, string $date = '2025-09-01', ?string $in = '09:00', ?string $out = '18:00'): Attendance
    {
        $att = new Attendance();
        $att->user_id = $user->id;
        $att->work_date = $date;
        $att->clock_in_at  = $in  ? Carbon::parse("$date $in",  $this->tz) : null;
        $att->clock_out_at = $out ? Carbon::parse("$date $out", $this->tz) : null;
        $att->save();

        return $att->refresh();
    }

    /** @test ① 出勤時間 > 退勤時間 → エラー表示 */
    public function 出勤時間が退勤時間より後になっている場合、エラーメッセージが表示される(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $att = $this->makeAttendance($user, '2025-09-01', '09:00', '10:00');
        $ymd = is_string($att->work_date)
            ? $att->work_date
            : Carbon::parse($att->work_date, $this->tz)->format('Y-m-d');

        $res = $this->from(route('attendance.detail', $att->id))
            ->post(route('attendance.request'), [
                'attendance_id' => $att->id,
                'date'          => $ymd,
                'clock_in_at'   => '19:00', // 退勤より後
                'clock_out_at'  => '10:00',
                'breaks'        => [],
                'reason'        => 'テスト',
            ]);

        $res->assertRedirect(route('attendance.detail', $att->id));

        $this->get(route('attendance.detail', $att->id))
            ->assertSee('出勤時間が不適切な値です', false);
    }

    /** @test ② 休憩開始 > 退勤 → エラー表示 */
    public function 休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示される(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $att = $this->makeAttendance($user, '2025-09-01', '09:00', '20:00');
        $ymd = is_string($att->work_date)
            ? $att->work_date
            : Carbon::parse($att->work_date, $this->tz)->format('Y-m-d');

        $res = $this->from(route('attendance.detail', $att->id))
            ->post(route('attendance.request'), [
                'attendance_id' => $att->id,
                'date'          => $ymd,
                'clock_in_at'   => '09:00',
                'clock_out_at'  => '20:00',
                'breaks'        => [
                    ['start_at' => '21:00', 'end_at' =>''], // 退勤より後
                ],
                'reason'        => 'テスト',
            ]);

        $res->assertRedirect(route('attendance.detail', $att->id));

        $this->get(route('attendance.detail', $att->id))
            ->assertSee('休憩時間が不適切な値です', false);
    }

    /** @test ③ 休憩終了 > 退勤 → エラー表示 */
    public function 休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示される(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $att = $this->makeAttendance($user, '2025-09-01', '09:00', '20:00');
        $ymd = is_string($att->work_date)
            ? $att->work_date
            : Carbon::parse($att->work_date, $this->tz)->format('Y-m-d');

        $res = $this->from(route('attendance.detail', $att->id))
            ->post(route('attendance.request'), [
                'attendance_id' => $att->id,
                'date'          => $ymd,
                'clock_in_at'   => '09:00',
                'clock_out_at'  => '20:00',
                'breaks'        => [
                    ['start_at' => '19:30', 'end_at' => '21:00'], // 終了が退勤より後
                ],
                'reason'        => 'テスト',
            ]);

        $res->assertRedirect(route('attendance.detail', $att->id));

        $this->get(route('attendance.detail', $att->id))
            ->assertSee('休憩時間もしくは退勤時間が不適切な値です', false);
    }

    /** @test ④ 備考未入力 → エラー表示 */
    public function 備考欄が未入力の場合のエラーメッセージが表示される(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $att = $this->makeAttendance($user);
        $ymd = is_string($att->work_date)
            ? $att->work_date
            : Carbon::parse($att->work_date, $this->tz)->format('Y-m-d');

        $res = $this->from(route('attendance.detail', $att->id))
            ->post(route('attendance.request'), [
                'attendance_id' => $att->id,
                'date'          => $ymd,
                'clock_in_at'   => '09:00',
                'clock_out_at'  => '18:00',
                'breaks'        => [],
                'reason'        => '', // 未入力
            ]);

        $res->assertRedirect(route('attendance.detail', $att->id));

        $this->get(route('attendance.detail', $att->id))
            ->assertSee('備考を記入してください', false);
    }

    /** @test ⑤ 修正申請が作成され、管理者一覧(承認待ち)に“表示される” */
    public function 修正申請処理が実行される(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $att = $this->makeAttendance($user, '2025-09-01', '09:00', '18:00');
        $ymd = is_string($att->work_date)
            ? $att->work_date
            : Carbon::parse($att->work_date, $this->tz)->format('Y-m-d');

        // 申請作成
        $this->post(route('attendance.request'), [
            'attendance_id' => $att->id,
            'date'          => $ymd,
            'clock_in_at'   => '09:05',
            'clock_out_at'  => '18:02',
            'breaks'        => [
                ['start_at' => '12:00', 'end_at' => '12:30'],
            ],
            'reason'        => '退勤押し忘れ',
        ])->assertRedirect();

        $this->assertDatabaseHas('attendance_edit_requests', [
            'attendance_id' => $att->id,
            'applicant_id'  => $user->id,
            'status'        => 'pending',
            'reason'        => '退勤押し忘れ',
        ]);

        // 管理画面の認証ミドルウェアを無効化して一覧の見た目を確認
        $this->withoutMiddleware(); // auth:admin も素通り
        $this->get(route('admin.requests.index', ['status' => 'pending']))
            ->assertOk()
            ->assertSee($user->name, false)
            ->assertSee('退勤押し忘れ', false);
    }

    /** @test ⑥ “承認待ち”に自分の申請が全て表示される */
    public function 「承認待ち」にログインユーザーが行った申請が全て表示されていること(): void
    {
        $me    = $this->makeUser(['email' => 'me@example.com']);
        $other = $this->makeUser(['email' => 'other@example.com', 'name' => '他人 次郎']);

        $att1 = $this->makeAttendance($me,    '2025-09-01', '09:00', '18:00');
        $att2 = $this->makeAttendance($me,    '2025-09-02', '09:00', '18:00');
        $att3 = $this->makeAttendance($other, '2025-09-01', '09:00', '18:00');

        AttendanceEditRequest::create([
            'applicant_id'      => $me->id,
            'attendance_id'     => $att1->id,
            'status'            => 'pending',
            'reason'            => 'Aの理由',
            'requested_changes' => [],
        ]);
        AttendanceEditRequest::create([
            'applicant_id'      => $me->id,
            'attendance_id'     => $att2->id,
            'status'            => 'pending',
            'reason'            => 'Bの理由',
            'requested_changes' => [],
        ]);
        AttendanceEditRequest::create([
            'applicant_id'      => $other->id,
            'attendance_id'     => $att3->id,
            'status'            => 'pending',
            'reason'            => '他人の理由',
            'requested_changes' => [],
        ]);

        $this->actingAs($me)
            ->get(route('my.requests.pending'))
            ->assertOk()
            ->assertSee('Aの理由', false)
            ->assertSee('Bの理由', false)
            ->assertDontSee('他人の理由', false);
    }

    /** @test ⑦ “承認済み”タブに承認済み申請が表示される */
    public function 「承認済み」に管理者が承認した修正申請が全て表示されている(): void
    {
        $user = $this->makeUser();
        $att  = $this->makeAttendance($user);

        $req = AttendanceEditRequest::create([
            'applicant_id'      => $user->id,
            'attendance_id'     => $att->id,
            'status'            => 'pending',
            'reason'            => '承認してね',
            'requested_changes' => [],
        ]);

        // 本来は管理画面から承認だが、ここでは DB 直接更新
        $req->status = 'approved';
        $req->reviewed_at = now();
        $req->save();

        $this->actingAs($user)
            ->get(route('my.requests.approved'))
            ->assertOk()
            ->assertSee('承認してね', false)
            ->assertSee($user->name, false);
    }

    /** @test ⑧ 申請一覧の「詳細」→ 申請詳細（勤怠詳細相当）が見られる */
    public function 各申請の「詳細」を押下すると勤怠詳細画面に遷移する(): void
    {
        $user = $this->makeUser();
        $att  = $this->makeAttendance($user, '2025-09-03', '09:00', '18:00');

        $req = AttendanceEditRequest::create([
            'applicant_id'      => $user->id,
            'attendance_id'     => $att->id,
            'status'            => 'pending',
            'reason'            => '詳細遷移テスト',
            'requested_changes' => [],
        ]);

        $this->actingAs($user)
            ->get(route('my.requests.show', $req->id))
            ->assertOk()
            ->assertSee('勤怠詳細', false)
            ->assertSee('詳細遷移テスト', false);
    }
}
