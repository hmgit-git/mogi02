@extends('layouts.app')
@section('title','勤怠詳細')
@section('body_class','theme-user')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance-common.css') }}">
@endsection

@section('content')
@php
use Illuminate\Support\Carbon;

// Controller からの想定値: $att, $dateLabel, $breakMinutes, $workedMinutes, $monthParam, $breaks
$userName = auth()->user()->name ?? '';
$dateYmd = isset($att->work_date) ? Carbon::parse($att->work_date,'Asia/Tokyo')->toDateString()
: (request()->route('date') ?? now('Asia/Tokyo')->toDateString());

// 既存休憩（なければ空）
$breaks = isset($breaks) ? $breaks : ($att ? $att->breaks->sortBy('start_at') : collect());
$extraIndex = $breaks->count(); // 追加用の空行
@endphp

<div class="att-wrap">
    <h1 class="section-title">勤怠詳細</h1>

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
        <input type="hidden" name="date" value="{{ $dateYmd }}">

        <table class="att-table">
            <tbody>
                {{-- 名前 --}}
                <tr>
                    <th>名前</th>
                    <td>{{ $userName }}</td>
                </tr>

                {{-- 日付（3列： (a)年 / (b)空欄 / (c)月日 ）--}}
                @php
                $dateObj = \Illuminate\Support\Carbon::parse($dateYmd, 'Asia/Tokyo');
                $yearStr = $dateObj->year . '年';
                $mdStr = $dateObj->format('n月j日'); // 先頭ゼロなし
                @endphp
                <tr>
                    <th>日付</th>
                    <td>
                        <div class="triple">
                            {{-- (a) 年：開始側の列（編集不可の静的ボックス） --}}
                            <div class="input input-static" aria-label="年">{{ $yearStr }}</div>

                            {{-- (b) セパレータ列は空欄（要件どおり） --}}
                            <span class="triple-sep"></span>

                            {{-- (c) 月日：終了側の列（編集不可の静的ボックス） --}}
                            <div class="input input-static" aria-label="月日">{{ $mdStr }}</div>
                        </div>
                    </td>
                </tr>


                {{-- 出勤・退勤（3列： (a)入力 (b)〜 (c)入力 ）--}}
                <tr>
                    <th>出勤・退勤</th>
                    <td>
                        <div class="triple">
                            <input class="input" type="time" name="clock_in" value="{{ old('clock_in',  $att?->clock_in_at?->format('H:i')) }}">
                            <span class="triple-sep">〜</span>
                            <input class="input" type="time" name="clock_out" value="{{ old('clock_out', $att?->clock_out_at?->format('H:i')) }}">
                        </div>
                    </td>
                </tr>

                {{-- 休憩（既存分） --}}
                @foreach ($breaks as $i => $br)
                @php
                $startVal = old("breaks.$i.start", $br->start_at?->format('H:i'));
                $endVal = old("breaks.$i.end", $br->end_at?->format('H:i'));
                @endphp
                <tr>
                    <th>{{ $i === 0 ? '休憩' : "休憩".($i+1) }}</th>
                    <td>
                        <div class="triple">
                            <input class="input" type="time" name="breaks[{{ $i }}][start]" value="{{ $startVal }}">
                            <span class="triple-sep">〜</span>
                            <input class="input" type="time" name="breaks[{{ $i }}][end]" value="{{ $endVal }}">
                        </div>
                        @error("breaks.$i.start")<div class="field-error">{{ $message }}</div>@enderror
                        @error("breaks.$i.end") <div class="field-error">{{ $message }}</div>@enderror
                    </td>
                </tr>
                @endforeach

                {{-- 休憩 追加用の空欄（1行） --}}
                <tr>
                    <th>{{ $breaks->isEmpty() ? '休憩' : '休憩'.($extraIndex+1) }}</th>
                    <td>
                        <div class="triple">
                            <input class="input" type="time" name="breaks[{{ $extraIndex }}][start]" value="{{ old("breaks.$extraIndex.start") }}">
                            <span class="triple-sep">〜</span>
                            <input class="input" type="time" name="breaks[{{ $extraIndex }}][end]" value="{{ old("breaks.$extraIndex.end") }}">
                        </div>
                        @error("breaks.$extraIndex.start")<div class="field-error">{{ $message }}</div>@enderror
                        @error("breaks.$extraIndex.end") <div class="field-error">{{ $message }}</div>@enderror
                    </td>
                </tr>

                {{-- 備考（必須） --}}
                <tr>
                    <th>備考</th>
                    <td>
                        <textarea class="input" name="note" rows="3" placeholder="修正理由・背景などを記入してください" required>{{ old('note') }}</textarea>
                        @error('note')<div class="field-error">{{ $message }}</div>@enderror
                    </td>
                </tr>
            </tbody>
        </table>

        {{-- 右下：修正ボタン（黒） --}}
        <div style="display:flex; gap:12px; margin-top:12px;">
            <button type="submit" class="btn btn-primary" style="margin-left:auto;">修正</button>
        </div>
    </form>

    {{-- 参考：下に現状値のサマリを残すなら（任意）
<div class="att-summary" style="max-width:760px;">
  実働：
  @php $wm = $workedMinutes ?? 0; @endphp
  @if ($wm>0) {{ intdiv($wm,60) }}時間{{ $wm%60 }}分 @else - @endif
</div>
--}}
</div>
@endsection