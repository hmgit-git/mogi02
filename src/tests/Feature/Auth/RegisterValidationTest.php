<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RegisterValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('ja'); // 日本語メッセージで検証
    }

    /** @test */
    public function 名前が未入力だと_お名前を入力してください_が表示される()
    {
        $response = $this->from('/register')->post('/register', [
            'name' => '', // ← わざと空
            'email' => 'user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('name');

        $this->get('/register')
            ->assertOk()
            ->assertSee('お名前を入力してください', false);
    }
    /** @test */
    public function メールアドレス未入力だと_メールアドレスを入力してください_が表示される()
    {
        $response = $this->from('/register')->post('/register', [
            'name' => '山田太郎',
            'email' => '', // ← わざと空
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // バリデーション NG → /register へ戻る
        $response->assertRedirect('/register');
        // email フィールドにエラーが付与される
        $response->assertSessionHasErrors('email');

        // 画面に日本語メッセージが出ること
        $this->get('/register')
            ->assertOk()
            ->assertSee('メールアドレスを入力してください', false);
    }
    /** @test */
    public function パスワードが8文字未満だと_パスワードは8文字以上で入力してください_が表示される()
    {
        $response = $this->from('/register')->post('/register', [
            'name' => '山田太郎',
            'email' => 'user@example.com',
            'password' => 'short',              // 5文字（わざと短く）
            'password_confirmation' => 'short', // 確認も同じにして、minエラーだけを見る
        ]);

        // バリデーションNG → /register に戻る
        $response->assertRedirect('/register');
        // password フィールドにエラーが付く
        $response->assertSessionHasErrors('password');

        // 画面に日本語メッセージ
        $this->get('/register')
            ->assertOk()
            ->assertSee('パスワードは8文字以上で入力してください', false);
    }
    /** @test */
    public function パスワード確認が一致しないと_パスワードと一致しません_が表示される()
    {
        $response = $this->from('/register')->post('/register', [
            'name' => '山田太郎',
            'email' => 'user@example.com',
            'password' => 'password123',              // 本体
            'password_confirmation' => 'password999', // わざと不一致
        ]);

        // バリデーションNG → /register に戻る
        $response->assertRedirect('/register');
        // password フィールドにエラーが付く（confirmed ルール）
        $response->assertSessionHasErrors('password');

        // 画面に日本語メッセージが出ること
        $this->get('/register')
            ->assertOk()
            ->assertSee('パスワードと一致しません', false);
    }
    /** @test */
    public function パスワード未入力だと_パスワードを入力してください_が表示される()
    {
        $response = $this->from('/register')->post('/register', [
            'name' => '山田太郎',
            'email' => 'user@example.com',
            'password' => '',                 // ← わざと空
            'password_confirmation' => '',    // 空で合わせる
        ]);

        // バリデーションNG → /register に戻る
        $response->assertRedirect('/register');
        // password にエラーが付く
        $response->assertSessionHasErrors('password');

        // 画面に日本語メッセージが表示される
        $this->get('/register')
            ->assertOk()
            ->assertSee('パスワードを入力してください', false);
    }
    /** @test */
    public function 正しい入力ならデータベースに登録したユーザー情報が保存される()
    {
        $payload = [
            'name' => '山田太郎',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->from('/register')->post('/register', $payload);

        // 成功時の遷移
        $response->assertStatus(302);

        // DB に保存されていること（パスワードのハッシュはここでは不問）
        $this->assertDatabaseHas('users', [
            'email' => 'taro@example.com',
            'name'  => '山田太郎',
        ]);
    }
}
