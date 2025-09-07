@extends('layouts.admin')
@section('title','勤怠詳細（管理）')
@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance-common.css') }}">
@endsection

@section('content')
@php
use Illuminate\Support\Carbon;

$tz = 'Asia/Tokyo';
$userName = $att->user?->name ?? '-';
$dateYmd = Carbon::parse($att->work_date, $tz)->toDateString();
$dateObj = Carbon::parse($dateYmd, $tz);
$yearStr = $dateObj->year . '年';
$mdStr = $dateObj->format('n月j日');

// 時刻初期値（H:i）
$inVal = $att?->clock_in_at ? Carbon::parse($att->clock_in_at, $tz)->format('H:i') : '';
$outVal = $att?->clock_out_at ? Carbon::parse($att->clock_out_at, $tz)->format('H:i') : '';

$breakItems = $att->breaks->sortBy('start_at')->map(function ($br) use ($tz) {
return [
'start_at' => $br->start_at ? Carbon::parse($br->start_at, $tz)->format('H:i') : '',
'end_at' => $br->end_at ? Carbon::parse($br->end_at, $tz)->format('H:i') : '',
];
})->values();

// 追加用の空行1つ
$breakItems->push(['start_at' => '', 'end_at' => '']);
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
            <li>{{ is_array($error) ? implode(' / ', $error) : $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('admin.attendances.update', $att->id) }}" class="att-edit-form" novalidate>
        @csrf
        @method('PUT')

        <table class="att-table">
            <tbody>
                <tr>
                    <th>名前</th>
                    <td>{{ $userName }}</td>
                </tr>

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

                <tr>
                    <th>出勤・退勤</th>
                    <td>
                        <div class="triple">
                            <input class="input" type="time" name="clock_in_at" value="{{ old('clock_in_at',  $inVal) }}">
                            <span class="triple-sep">〜</span>
                            <input class="input" type="time" name="clock_out_at" value="{{ old('clock_out_at', $outVal) }}">
                        </div>
                    </td>
                </tr>

                @foreach ($breakItems as $i => $br)
                <tr>
                    <th>{{ $i === 0 ? '休憩' : '休憩'.($i+1) }}</th>
                    <td>
                        <div class="triple">
                            <input class="input" type="time" name="breaks[{{ $i }}][start_at]" value="{{ old("breaks.$i.start_at", $br['start_at']) }}">
                            <span class="triple-sep">〜</span>
                            <input class="input" type="time" name="breaks[{{ $i }}][end_at]" value="{{ old("breaks.$i.end_at",   $br['end_at']) }}">
                        </div>
                    </td>
                </tr>
                @endforeach

                <tr>
                    <th>備考</th>
                    <td>
                        <textarea class="input" name="reason" rows="3" placeholder="更新内容のメモ（任意）">{{ old('reason', $att->note) }}</textarea>
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