<?php

namespace Tests\Feature\Admin\Auth;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminLoginValidationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function メールアドレス未入力だと_メールアドレスを入力してください_が表示される()
    {
        // 入力：email なし
        $res = $this->from('/admin/login')->post('/admin/login', [
            'email'    => '',
            'password' => 'secret',
        ]);

        // セッションに email のエラー
        $res->assertSessionHasErrors('email');

        // 画面に期待メッセージ
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('メールアドレスを入力してください', false);
    }

    /** @test */
    public function パスワード未入力だと_パスワードを入力してください_が表示される()
    {
        // 入力：password なし
        $res = $this->from('/admin/login')->post('/admin/login', [
            'email'    => 'admin@example.com',
            'password' => '',
        ]);

        // セッションに password のエラー
        $res->assertSessionHasErrors('password');

        // 画面に期待メッセージ
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('パスワードを入力してください', false);
    }

    /** @test */
    public function 登録内容と一致しないと_ログイン情報が登録されていません_が表示される()
    {
        // 存在しない資格情報でログイン試行
        $res = $this->from('/admin/login')->post('/admin/login', [
            'email'    => 'nope@example.com',
            'password' => 'wrong-password',
        ]);

        // 想定：一般的な認証失敗メッセージを表示
        $res->assertSessionHasErrors(); // 何らかのエラーがある

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('ログイン情報が登録されていません', false);
    }
}
