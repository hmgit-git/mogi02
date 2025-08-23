@extends('layouts.app')
@section('title','勤怠詳細')

@section('css')
{{-- 共通のカード/ボタン/配色 --}}
<link rel="stylesheet" href="{{ asset('css/auth-common.css') }}">
{{-- 勤怠ページ専用（バッジ・テーブル等） --}}
<link rel="stylesheet" href="{{ asset('css/attendance-common.css') }}">
@endsection

@section('content')
@php
use Illuminate\Support\Carbon;
$dDate = Carbon::parse($display['date']);
$youbi = ['日','月','火','水','木','金','土'][$dDate->dayOfWeek];
@endphp

<div class="auth-container">
    <div class="auth-card">
        <h1 class="auth-title">勤怠詳細</h1>

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

        <table class="att-table">
            <tbody>
                <tr>
                    <th>日付</th>
                    <td>{{ $dDate->format('Y年n月j日') }}({{ $youbi }})</td>
                </tr>
                <tr>
                    <th>出勤</th>
                    <td>{{ $display['clock_in'] ?? '-' }}</td>
                </tr>
                <tr>
                    <th>休憩開始</th>
                    <td>{{ $display['break_start'] ?? '-' }}</td>
                </tr>
                <tr>
                    <th>休憩終了</th>
                    <td>{{ $display['break_end'] ?? '-' }}</td>
                </tr>
                <tr>
                    <th>退勤</th>
                    <td>{{ $display['clock_out'] ?? '-' }}</td>
                </tr>
                <tr>
                    <th>実働</th>
                    <td>
                        @if (($display['work_min'] ?? 0) > 0)
                        {{ intdiv($display['work_min'],60) }}時間{{ $display['work_min'] % 60 }}分
                        @else
                        -
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>備考</th>
                    <td>{{ $att->note ?? '' }}</td>
                </tr>
                <tr>
                    <th>状態</th>
                    <td>{{ $att->status ?? '' }}</td>
                </tr>
            </tbody>
        </table>

        <div class="att-actions" style="margin-top:16px; display:flex; gap:12px; flex-wrap:wrap;">
            <a class="link" href="{{ route('attendance.list', ['month' => \Illuminate\Support\Str::of($display['date'])->substr(0,7)]) }}">← 一覧に戻る</a>
        </div>
    </div>
</div>
@endsection