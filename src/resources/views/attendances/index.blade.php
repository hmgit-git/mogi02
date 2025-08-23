@extends('layouts.app')

@section('title','勤怠ダッシュボード')
@section('body_class','theme-user')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance-common.css') }}">
@endsection

@section('content')
@php
use Illuminate\Support\Carbon;
$date = Carbon::parse($today ?? now('Asia/Tokyo'));
$youbi = ['日','月','火','水','木','金','土'][$date->dayOfWeek];

// 状態ラベル
$stateLabel = [
'not_started' => '勤務外',
'working' => '出勤中',
'on_break' => '休憩中',
'finished' => '退勤済',
][$state ?? 'not_started'] ?? '勤務外';

// バッジ用クラス
$stateClass = match($state ?? 'not_started') {
'working' => 'state-working',
'on_break' => 'state-break',
'finished' => 'state-finished',
default => 'state-off',
};
@endphp

<div class="auth-container">
    <div class="auth-card att-hero">

        {{-- 状態バッジ --}}
        <div class="state-badge {{ $stateClass }}">{{ $stateLabel }}</div>

        {{-- 年月日(曜日) --}}
        <div class="att-date">{{ $date->format('Y年n月j日') }}({{ $youbi }})</div>

        {{-- 現時間 --}}
        <div class="att-time"><span id="nowTime">--:--:--</span></div>

        {{-- フラッシュメッセージ（任意） --}}
        @if (session('status'))
        <div class="alert alert-info">{{ session('status') }}</div>
        @endif
        @if (session('auth_error'))
        <div class="alert alert-error">{{ session('auth_error') }}</div>
        @endif

        {{-- アクション：状態で切り替え --}}
        @if ($state === 'not_started')
        {{-- 出勤前：出勤ボタンのみ --}}
        <form method="POST" action="{{ route('attendance.punch.in') }}" class="att-actions">
            @csrf
            <button class="btn btn-primary" type="submit">出勤</button>
        </form>

        @elseif ($state === 'working')
        {{-- 出勤中：退勤(黒) + 休憩入(白) --}}
        <div class="btn-row">
            <form method="POST" action="{{ route('attendance.punch.out') }}" class="att-actions">@csrf
                <button class="btn btn-primary" type="submit">退勤</button>
            </form>
            <form method="POST" action="{{ route('attendance.break.start') }}" class="att-actions">@csrf
                <button class="btn btn-secondary" type="submit">休憩入</button>
            </form>
        </div>

        @elseif ($state === 'on_break')
        {{-- 休憩中：退勤(黒) + 休憩戻(白) --}}
        <div class="btn-row">
            <form method="POST" action="{{ route('attendance.punch.out') }}" class="att-actions">@csrf
                <button class="btn btn-primary" type="submit">退勤</button>
            </form>
            <form method="POST" action="{{ route('attendance.break.end') }}" class="att-actions">@csrf
                <button class="btn btn-secondary" type="submit">休憩戻</button>
            </form>
        </div>

        @elseif ($state === 'finished')
        {{-- 退勤済み：ボタン無し --}}
        <p style="margin-top:8px;">お疲れ様でした。</p>
        @endif

    </div>
</div>

{{-- 時計＆二重送信防止 --}}
<script>
    (function() {
        // 時計
        const el = document.getElementById('nowTime');

        function pad(n) {
            return n.toString().padStart(2, '0')
        }

        function tick() {
            const now = new Date(new Date().toLocaleString('ja-JP', {
                timeZone: 'Asia/Tokyo'
            }));
            el.textContent = `${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
        }
        tick();
        setInterval(tick, 1000);

        // 送信時にボタン無効化（連打防止）
        document.querySelectorAll('.att-actions').forEach(f => {
            f.addEventListener('submit', () => {
                const b = f.querySelector('button');
                if (b) {
                    b.disabled = true;
                }
            });
        });
    })();
</script>
@endsection