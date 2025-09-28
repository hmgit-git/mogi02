@extends('layouts.app')
@section('title','勤怠詳細')
@section('body_class','theme-user')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance-common.css') }}">
@endsection

@section('content')
@php
use Illuminate\Support\Carbon;

/**
* Controller からの想定値:
* - $att (Attendance with breaks,user)
* - $breaks: 既存休憩（なければnull/未定）
*/

$tz = 'Asia/Tokyo';

// 表示用の氏名
$userName = auth()->user()->name ?? '';

// 対象日（YYYY-MM-DD）
$dateYmd = isset($att->work_date)
? Carbon::parse($att->work_date, $tz)->toDateString()
: (request()->route('date') ?? now($tz)->toDateString());

// 出退勤の初期値（H:i のみ）
$inVal = $att?->clock_in_at ? Carbon::parse($att->clock_in_at, $tz)->format('H:i') : '';
$outVal = $att?->clock_out_at ? Carbon::parse($att->clock_out_at, $tz)->format('H:i') : '';

// 休憩：コレクション→配列化（H:i のみ）
$breakItems = collect($breaks ?? ($att ? $att->breaks->sortBy('start_at') : collect()))
->map(function ($br) use ($tz) {
return [
'start_at' => $br->start_at ? Carbon::parse($br->start_at, $tz)->format('H:i') : '',
'end_at' => $br->end_at ? Carbon::parse($br->end_at, $tz)->format('H:i') : '',
];
})
->values();

// 追加用の空行を1つ足す（ユーザーが手入力で追加できるように）
$breakItems->push(['start_at' => '', 'end_at' => '']);

// 見出し用（年・月日）
$dateObj = Carbon::parse($dateYmd, $tz);
$yearStr = $dateObj->year . '年';
$mdStr = $dateObj->format('n月j日');
@endphp

<div class="att-wrap">
    <h1 class="section-title">勤怠詳細</h1>

    {{-- 上部：フラッシュ/エラーまとめ --}}
    @if (session('status'))
    <div class="alert alert-info">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
    <div class="alert alert-error">
        <ul class="error-list">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('attendance.request') }}" class="att-edit-form" novalidate>
        @csrf

        {{-- 申請対象の勤怠ID（必須） --}}
        <input type="hidden" name="attendance_id" value="{{ $att->id }}">
        {{-- 時刻合成用に当日付も送っておく（FormRequestで結合して検証・保存） --}}
        <input type="hidden" name="date" value="{{ $dateYmd }}">

        <table class="att-table">
            <tbody>
                {{-- 名前 --}}
                <tr>
                    <th>名前</th>
                    <td>{{ $userName }}</td>
                </tr>

                {{-- 日付（3列：年 / 空欄 / 月日）※編集不可表示 --}}
                <tr>
                    <th>日付</th>
                    <td>
                        <div class="triple">
                            <div class="input input-static" aria-label="年">{{ $yearStr }}</div>
                            <span class="triple-sep"></span>
                            <div class="input input-static" aria-label="月日">{{ $mdStr }}</div>
                        </div>
                    </td>
                </tr>

                {{-- 出勤・退勤（時刻のみ H:i） --}}
                <tr>
                    <th>出勤・退勤</th>
                    <td>
                        <div class="triple">
                            <input
                                class="input"
                                type="time"
                                name="clock_in_at"
                                value="{{ old('clock_in_at', $inVal) }}">
                            <span class="triple-sep">〜</span>
                            <input
                                class="input"
                                type="time"
                                name="clock_out_at"
                                value="{{ old('clock_out_at', $outVal) }}">
                        </div>
                    </td>
                </tr>

                {{-- 休憩（既存分 + 追加1行） --}}
                @foreach ($breakItems as $i => $br)
                <tr>
                    <th>{{ $i === 0 ? '休憩' : '休憩'.($i+1) }}</th>
                    <td>
                        <div class="triple">
                            <input
                                class="input"
                                type="time"
                                name="breaks[{{ $i }}][start_at]"
                                value="{{ old("breaks.$i.start_at", $br['start_at']) }}">
                            <span class="triple-sep">〜</span>
                            <input
                                class="input"
                                type="time"
                                name="breaks[{{ $i }}][end_at]"
                                value="{{ old("breaks.$i.end_at", $br['end_at']) }}">
                        </div>
                    </td>
                </tr>
                @endforeach

                {{-- 備考（必須） --}}
                <tr>
                    <th>備考</th>
                    <td>
                        <textarea
                            class="input"
                            name="reason"
                            rows="3"
                            placeholder="修正理由・背景などを記入してください"
                            required>{{ old('reason') }}</textarea>
                    </td>
                </tr>
            </tbody>
        </table>

        {{-- 右下：修正ボタン --}}
        <div class="form-actions-right">
            <button type="submit" class="btn-submit">修正</button>
        </div>
    </form>
</div>
@endsection