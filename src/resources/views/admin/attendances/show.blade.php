@extends('layouts.admin')
@section('title','勤怠詳細')
@section('body_class','theme-admin')

@section('content')
<div class="auth-container">
    <div class="auth-card">
        <h1 class="auth-title">勤怠詳細</h1>

        <div style="display:grid; gap:12px;">
            <div><strong>名前：</strong>{{ $att->user?->name ?? '-' }}</div>
            <div><strong>日付：</strong>{{ $dateLabel }}</div>
            <div><strong>出勤：</strong>{{ $att->clock_in_at ? $att->clock_in_at->format('H:i') : '-' }}</div>
            <div><strong>退勤：</strong>{{ $att->clock_out_at ? $att->clock_out_at->format('H:i') : '-' }}</div>

            <div>
                <strong>休憩：</strong>
                <div style="margin-top:6px;">
                    @forelse($att->breaks as $i => $br)
                    <div>休憩{{ $i+1 }}：{{ $br->start_at?->format('H:i') }} 〜 {{ $br->end_at?->format('H:i') ?? '--:--' }}</div>
                    @empty
                    <div>（休憩なし）</div>
                    @endforelse
                </div>
            </div>

            <div><strong>合計：</strong>
                @php
                $h = intdiv($workedMinutes,60); $m = $workedMinutes%60;
                echo sprintf('%d:%02d', $h, $m);
                @endphp
            </div>

            @if($att->note)
            <div><strong>備考：</strong>{{ $att->note }}</div>
            @endif
        </div>
    </div>
</div>
@endsection