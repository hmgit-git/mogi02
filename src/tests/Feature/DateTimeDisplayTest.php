<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Carbon;

class DateTimeDisplayTest extends TestCase
{
    /** @test */
    public function 勤怠打刻画面に現在の日時が所定形式で表示される()
    {
        // テストを安定化（分またぎ対策）
        Carbon::setTestNow(Carbon::parse('2025-09-01 12:34:00', 'Asia/Tokyo'));

        // 認証（メール認証済みで /attendance に入れるように）
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        // 画面表示
        $res  = $this->get('/attendance')->assertOk();
        $html = $res->getContent();

        $nowJst = now('Asia/Tokyo');

        // --- 日付の表記揺れを両対応 ---
        // 1) スラッシュ形式: 2025/09/01
        $expectedDateSlash = $nowJst->format('Y/m/d');

        // 2) 日本語形式: 2025年9月1日(月)
        $dowJa = ['日', '月', '火', '水', '木', '金', '土'][$nowJst->dayOfWeek];
        $expectedDateJa = $nowJst->format('Y年n月j日') . '(' . $dowJa . ')';

        $this->assertTrue(
            str_contains($html, $expectedDateSlash) || str_contains($html, $expectedDateJa),
            "日付が {$expectedDateSlash} または {$expectedDateJa} で表示されていません。"
        );

        // --- 時刻（HH:MM または HH:MM:SS を許容）---
        $hhmm = $nowJst->format('H:i');
        $this->assertMatchesRegularExpression(
            '/\b' . preg_quote($hhmm, '/') . '(?::\d{2})?\b/',
            $html,
            "時刻が {$hhmm}（または末尾に秒付き）で表示されていません。"
        );
    }
}
