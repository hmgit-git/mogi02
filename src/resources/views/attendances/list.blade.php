@extends('layouts.app')
@section('title','勤怠一覧')

@section('css')
{{-- 共通カード/ボタン --}}
<link rel="stylesheet" href="{{ asset('css/auth-common.css') }}">
{{-- 勤怠ページ専用（テーブル等） --}}
<link rel="stylesheet" href="{{ asset('css/attendance-common.css') }}">
@endsection

@section('content')
@php
use Illuminate\Support\Carbon;
@endphp

<div class="auth-container">
    <div class="auth-card">
        <h1 class="auth-title">勤怠一覧（{{ $month }}）</h1>

        {{-- 月切り替え --}}
        <form method="GET" action="{{ route('attendance.list') }}" class="att-filter">
            <label class="auth-label" for="month">対象月</label>
            <input id="month" class="input" type="month" name="month" value="{{ $month }}">
            <button class="btn btn-primary" type="submit">表示</button>
        </form>

        <table class="att-table att-table--list">
            <thead>
                <tr>
                    <th>日付</th>
                    <th>出勤</th>
                    <th>休憩開始</th>
                    <th>休憩終了</th>
                    <th>退勤</th>
                    <th>実働</th>
                    <th>備考</th>
                    <th>状態</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($days as $d)
                @php
                $dDate = Carbon::parse($d['date']);
                $youbi = ['日','月','火','水','木','金','土'][$dDate->dayOfWeek];
                $dateDisp = $dDate->format('Y年n月j日') . "($youbi)";
                $attId = $rows[$d['date']]->id ?? null;
                @endphp
                <tr>
                    <td>
                        @if ($attId)
                        <a class="link" href="{{ route('attendance.detail', ['id' => $attId]) }}">{{ $dateDisp }}</a>
                        @else
                        {{ $dateDisp }}
                        @endif
                    </td>
                    <td>{{ $d['clock_in_at'] ?? '-' }}</td>
                    <td>{{ $d['break_start'] ?? '-' }}</td>
                    <td>{{ $d['break_end'] ?? '-' }}</td>
                    <td>{{ $d['clock_out_at'] ?? '-' }}</td>
                    <td>
                        @if (($d['work_min'] ?? 0) > 0)
                        {{ intdiv($d['work_min'],60) }}時間{{ $d['work_min'] % 60 }}分
                        @else
                        -
                        @endif
                    </td>
                    <td>{{ $d['note'] ?? '' }}</td>
                    <td>{{ $d['status'] ?? '' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="att-summary">
            月合計：
            @php
            $h = intdiv($totalWorkMinutes, 60);
            $m = $totalWorkMinutes % 60;
            @endphp
            <strong>{{ $h }}時間{{ $m }}分</strong>
        </div>

        <div class="att-actions" style="margin-top:16px;">
            <a class="link" href="{{ route('attendance.index') }}">← ダッシュボードへ戻る</a>
        </div>
    </div>
</div>
@endsection