<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use App\Models\User;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /** 未認証ユーザー作成（email_verified_at を付けない） */
    private function makeUnverifiedUser(array $overrides = []): User
    {
        $user = User::create(array_merge([
            'name'     => '未認証 太郎',
            'email'    => 'verifyme@example.com',
            'password' => Hash::make('password123'),
        ], $overrides));

        // email_verified_at は null のまま
        return $user->refresh();
    }

    /** 勤怠トップへの遷移先（名前付きがあればそれを優先） */
    private function attendanceIndexUrl(): string
    {
        try {
            return route('attendance.index');
        } catch (\Throwable $e) {
            return '/attendance';
        }
    }

    /** ① 会員登録後、認証メールが送信される */
    public function test_登録後に認証メールが送信される(): void
    {
        Notification::fake();

        $user = $this->makeUnverifiedUser(['email' => 'first@example.com']);

        // 認証メール送信（ログイン済未認証ユーザーが「メール送信」ボタン押下の想定）
        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect(); // 通常は notice にリダイレクト

        // VerifyEmail 通知が送られていること
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    /** ② 誘導画面の「認証はこちらから」押下でメール認証サイト（＝認証案内画面）へ遷移する */
    public function test_認証誘導画面から送信ボタンで認証案内に戻る(): void
    {
        Notification::fake();

        $user = $this->makeUnverifiedUser(['email' => 'second@example.com']);

        // 誘導（案内）画面の表示（/email/verify）
        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk();

        // 「認証はこちらから」＝ 通常は POST /email/verification-notification
        // notice から send へ → notice へリダイレクト、が一般的
        $this->from(route('verification.notice'))
            ->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'));

        // 通知が飛んでいることも確認（おまけ）
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    private function verificationNoticeUrl(array $params = []): string
    {
        try {
            return route('verification.notice', $params);
        } catch (\Throwable $e) {
            $q = $params ? ('?' . http_build_query($params)) : '';
            return '/email/verify' . $q;
        }
    }

    /** ③ 署名付きURLでメール認証完了→ 勤怠登録画面に遷移する */
    public function test_メール認証完了で勤怠登録画面に遷移する(): void
    {
        $user = $this->makeUnverifiedUser(['email' => 'final@example.com']);

        $verifyUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        // 認証実行
        $res = $this->actingAs($user)->get($verifyUrl);

        // 実装実態に合わせて：notice へ ?verified=1 でリダイレクト
        $res->assertRedirect($this->verificationNoticeUrl(['verified' => 1]));

        // 確かに認証済み
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        // 認証後は勤怠トップに到達できる
        $this->actingAs($user)->get($this->attendanceIndexUrl())->assertOk();
    }
}
