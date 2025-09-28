@extends('layouts.app')
@section('title','勤怠詳細')
@section('body_class','theme-user')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance-common.css') }}">
@endsection

@section('content')
@php
$tz = 'Asia/Tokyo';
$changes = $req->requested_changes ?? [];
$userName = $req->applicant?->name ?? (auth()->user()->name ?? '');

// 対象日（見出し用：年と月日）
$targetDateStr = $req->attendance?->work_date
?? ($changes['clock_in_at'] ?? $changes['clock_out_at'] ?? null);

if ($targetDateStr) {
$dObj = \Illuminate\Support\Carbon::parse($targetDateStr, $tz);
$yearStr = $dObj->year . '年';
$mdStr = $dObj->format('n月j日');
} else {
$yearStr = $mdStr = '';
}

/** 値から HH:MM を取り出す（YYYY-mm-dd HH:ii でも HH:ii でもOK） */
$fmtTime = function($v) use ($tz) {
if (!$v) return '-';
if (preg_match('/^\d{1,2}:\d{2}$/', $v)) return $v;
try { return \Illuminate\Support\Carbon::parse($v, $tz)->format('H:i'); }
catch (\Throwable $e) { return '-'; }
};

$inDisp = $fmtTime($changes['clock_in_at'] ?? null);
$outDisp = $fmtTime($changes['clock_out_at'] ?? null);
$breakChanges = (is_array($changes['breaks'] ?? null)) ? $changes['breaks'] : [];
@endphp

<div class="att-wrap">
    <h1 class="section-title">勤怠詳細</h1>

    <table class="att-table">
        <tbody>
            <tr>
                <th>名前</th>
                <td>
                    <div class="input input-static">{{ $userName }}</div>
                </td>
            </tr>

            <tr>
                <th>日付</th>
                <td>
                    <div class="triple">
                        <div class="input input-static">{{ $yearStr }}</div>
                        <span class="triple-sep"></span>
                        <div class="input input-static">{{ $mdStr }}</div>
                    </div>
                </td>
            </tr>

            <tr>
                <th>出勤・退勤</th>
                <td>
                    <div class="triple">
                        <div class="input input-static">{{ $inDisp }}</div>
                        <span class="triple-sep">〜</span>
                        <div class="input input-static">{{ $outDisp }}</div>
                    </div>
                </td>
            </tr>

            {{-- 休憩：ゼロ件なら一切非表示 --}}
            @foreach($breakChanges as $i => $b)
            @php
            $start = $fmtTime($b['start_at'] ?? null);
            $end = $fmtTime($b['end_at'] ?? null);
            @endphp
            <tr>
                <th>{{ $i === 0 ? '休憩' : '休憩'.($i+1) }}</th>
                <td>
                    <div class="triple">
                        <div class="input input-static">{{ $start }}</div>
                        <span class="triple-sep">〜</span>
                        <div class="input input-static">{{ $end }}</div>
                    </div>
                </td>
            </tr>
            @endforeach

            <tr>
                <th>備考</th>
                <td>
                    <div class="input-static-note">{{ $req->reason }}</div>
                </td>
            </tr>
        </tbody>
    </table>

    {{-- 下部メッセージ（赤文字・右寄せ・枠なし） --}}
    <div class="att-pending-note">
        *承認待ちのため修正はできません。
    </div>
</div>
@endsection