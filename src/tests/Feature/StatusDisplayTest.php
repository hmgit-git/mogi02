<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StatusDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create([
            'email_verified_at' => now(), // verified ミドルウェアを通す
        ]);
    }

    /** @test */
    public function 勤務外ステータスが表示される()
    {
        $user = $this->verifiedUser();
        $this->actingAs($user);

        $this->get('/attendance')
            ->assertOk()
            ->assertSee('勤務外', false)
            ->assertDontSee('出勤中', false)
            ->assertDontSee('休憩中', false)
            ->assertDontSee('退勤済', false);
    }

    /** @test */
    public function 出勤中ステータスが表示される()
    {
        $user = $this->verifiedUser();
        $this->actingAs($user);

        // 出勤
        $this->post('/attendance/in')->assertRedirect();

        $this->get('/attendance')
            ->assertOk()
            ->assertSee('出勤中', false)   // ← 要件どおり“出勤中”を厳密に
            ->assertDontSee('勤務外', false)
            ->assertDontSee('休憩中', false)
            ->assertDontSee('退勤済', false);
    }

    /** @test */
    public function 休憩中ステータスが表示される()
    {
        $user = $this->verifiedUser();
        $this->actingAs($user);

        // 出勤 → 休憩開始
        $this->post('/attendance/in')->assertRedirect();
        $this->post('/attendance/break/start')->assertRedirect();

        $this->get('/attendance')
            ->assertOk()
            ->assertSee('休憩中', false)
            ->assertDontSee('勤務外', false)
            ->assertDontSee('出勤中', false)
            ->assertDontSee('退勤済', false);
    }

    /** @test */
    public function 退勤済ステータスが表示される()
    {
        $user = $this->verifiedUser();
        $this->actingAs($user);

        // 出勤 → 退勤
        $this->post('/attendance/in')->assertRedirect();
        $this->post('/attendance/out')->assertRedirect();

        $this->get('/attendance')
            ->assertOk()
            ->assertSee('退勤済', false)   // ← 要件どおり“退勤済”を厳密に
            ->assertDontSee('勤務外', false)
            ->assertDontSee('出勤中', false)
            ->assertDontSee('休憩中', false);
    }
}
