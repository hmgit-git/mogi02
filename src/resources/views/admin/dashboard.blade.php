@extends('layouts.admin')
@section('title', '管理者ダッシュボード')

@section('content')
<div class="auth-container">
    <div class="auth-card">
        <h1 class="auth-title" style="margin-bottom:12px;">管理者ダッシュボード</h1>

        <form method="POST" action="{{ route('admin.logout') }}" novalidate style="margin-bottom:16px;">
            @csrf
        </form>

        <div style="overflow:auto;">
            <table class="table" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="text-align:left; border-bottom:1px solid #ddd; padding:8px;">ユーザー</th>
                        <th style="text-align:left; border-bottom:1px solid #ddd; padding:8px;">日付</th>
                        <th style="text-align:left; border-bottom:1px solid #ddd; padding:8px;">出勤</th>
                        <th style="text-align:left; border-bottom:1px solid #ddd; padding:8px;">休憩開始</th>
                        <th style="text-align:left; border-bottom:1px solid #ddd; padding:8px;">休憩終了</th>
                        <th style="text-align:left; border-bottom:1px solid #ddd; padding:8px;">退勤</th>
                        <th style="text-align:left; border-bottom:1px solid #ddd; padding:8px;">ステータス</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attendances as $a)
                    <tr>
                        <td style="border-bottom:1px solid #eee; padding:8px;">{{ $a->user->name ?? '-' }}</td>
                        <td style="border-bottom:1px solid #eee; padding:8px;">{{ optional($a->work_date)->timezone('Asia/Tokyo')->toDateString() }}</td>
                        <td style="border-bottom:1px solid #eee; padding:8px;">{{ optional($a->clock_in_at)->timezone('Asia/Tokyo') }}</td>
                        <td style="border-bottom:1px solid #eee; padding:8px;">
                            {{-- 直近1件だけ出したい場合はリレーションから取って表示してもOK --}}
                            @php $br = optional($a->breaks)->first(); @endphp
                            {{ optional($br?->start_at)->timezone('Asia/Tokyo') }}
                        </td>
                        <td style="border-bottom:1px solid #eee; padding:8px;">
                            {{ optional($br?->end_at)->timezone('Asia/Tokyo') }}
                        </td>
                        <td style="border-bottom:1px solid #eee; padding:8px; font-weight:600;">
                            {{ optional($a->clock_out_at)->timezone('Asia/Tokyo') ?? '-' }}
                        </td>
                        <td style="border-bottom:1px solid #eee; padding:8px;">
                            @php
                            $label = [
                            'not_started' => '勤務外',
                            'working' => '出勤中',
                            'on_break' => '休憩中',
                            'finished' => '退勤済',
                            ][$a->status] ?? $a->status;
                            @endphp
                            {{ $label }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" style="padding:12px; text-align:center; color:#666;">データがありません</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection