@extends('layouts.admin') 
@section('title', '2025年8月31日の勤怠') {{-- 動的タイトルでもOK --}}
@section('body_class','theme-admin')

@section('content')
<div class="auth-container">
    <div class="auth-card">

        {{-- 左上：縦棒＋見出し --}}
        <h1 class="auth-title" style="display:flex; align-items:center; gap:10px;">
            <span style="display:inline-block; width:4px; height:26px; background:#000; border-radius:2px;"></span>
            <span>{{ $dateTitle }}の勤怠</span>
        </h1>

        {{-- 前月 / 中央日付 / 翌月 --}}
        <div class="actions" style="justify-content:center; margin-bottom:12px;">
            <a class="link" href="{{ route('admin.attendances.daily', ['date' => $prevDate]) }}">← 前月</a>
            <div style="display:flex; align-items:center; gap:8px;">
                <span aria-hidden="true">📅</span>
                <strong>{{ $centerYmd }}</strong>
            </div>
            <a class="link" href="{{ route('admin.attendances.daily', ['date' => $nextDate]) }}">翌月 →</a>
        </div>

        {{-- テーブル --}}
        <div style="overflow:auto;">
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="text-align:left; border-bottom:1px solid #ddd; padding:8px;">名前</th>
                        <th style="text-align:left; border-bottom:1px solid #ddd; padding:8px;">出勤</th>
                        <th style="text-align:left; border-bottom:1px solid #ddd; padding:8px;">退勤</th>
                        <th style="text-align:left; border-bottom:1px solid #ddd; padding:8px;">休憩</th>
                        <th style="text-align:left; border-bottom:1px solid #ddd; padding:8px;">合計</th>
                        <th style="text-align:left; border-bottom:1px solid #ddd; padding:8px;">詳細</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                    <tr>
                        <td style="border-bottom:1px solid #eee; padding:8px;">{{ $r['name'] }}</td>
                        <td style="border-bottom:1px solid #eee; padding:8px;">{{ $r['clock_in'] }}</td>
                        <td style="border-bottom:1px solid #eee; padding:8px;">{{ $r['clock_out'] }}</td>
                        <td style="border-bottom:1px solid #eee; padding:8px;">{{ $r['break_hm'] }}</td>
                        <td style="border-bottom:1px solid #eee; padding:8px;">{{ $r['work_hm'] }}</td>
                        <td style="border-bottom:1px solid #eee; padding:8px;">
                            <a class="link" href="{{ route('admin.attendances.show', $r['id']) }}"><strong>詳細</strong></a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" style="padding:12px; text-align:center; color:#666;">データがありません</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection