<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;

class LoginValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('ja'); // 日本語メッセージで検証
    }

    /** @test */
    public function メールアドレス未入力だと_メールアドレスを入力してください_が表示される()
    {
        // 手順1: ユーザーを登録
        User::factory()->create([
            'email' => 'taro@example.com',
            'password' => bcrypt('password123'),
        ]);

        // 手順2-3: email を未入力でログイン
        $res = $this->from('/login')->post('/login', [
            'email' => '',
            'password' => 'password123',
        ]);

        $res->assertRedirect('/login');
        $res->assertSessionHasErrors('email');

        $this->get('/login')
            ->assertOk()
            ->assertSee('メールアドレスを入力してください', false);
    }

    /** @test */
    public function パスワード未入力だと_パスワードを入力してください_が表示される()
    {
        // 手順1: ユーザーを登録
        User::factory()->create([
            'email' => 'taro@example.com',
            'password' => bcrypt('password123'),
        ]);

        // 手順2-3: password を未入力でログイン
        $res = $this->from('/login')->post('/login', [
            'email' => 'taro@example.com',
            'password' => '',
        ]);

        $res->assertRedirect('/login');
        $res->assertSessionHasErrors('password');

        $this->get('/login')
            ->assertOk()
            ->assertSee('パスワードを入力してください', false);
    }

    /** @test */
    public function 登録内容と一致しないと_ログイン情報が登録されていません_が表示される()
    {
        // 手順1: ユーザーを登録
        User::factory()->create([
            'email' => 'taro@example.com',
            'password' => bcrypt('password123'),
        ]);

        // 手順2-3: 存在しないメールアドレスでログイン
        $res = $this->from('/login')->post('/login', [
            'email' => 'wrong@example.com',
            'password' => 'password123',
        ]);

        $res->assertRedirect('/login');
        // Fortify/Breeze では失敗時に通常 'email' にエラーが入る
        $res->assertSessionHasErrors('email');

        $this->get('/login')
            ->assertOk()
            ->assertSee('ログイン情報が登録されていません', false);
    }
}
