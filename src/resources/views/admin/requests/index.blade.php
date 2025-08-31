@extends('layouts.admin')
@section('title','修正申請一覧')
@section('body_class','theme-admin')

@section('content')
<div class="auth-container">
    <div class="auth-card">
        <h1 class="auth-title">修正申請一覧</h1>

        @if (session('status'))
        <div class="alert alert-info">{{ session('status') }}</div>
        @endif

        {{-- タブ（承認待ち / 承認済み） --}}
        <div class="actions" style="margin-bottom:8px;">
            <a class="link" href="{{ route('admin.requests.index', ['status' => 'pending']) }}">承認待ち</a>
            <a class="link" href="{{ route('admin.requests.index', ['status' => 'approved']) }}">承認済み</a>
        </div>

        <div style="overflow:auto;">
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="text-align:left; border-bottom:1px solid #ddd; padding:8px;">状態</th>
                        <th style="text-align:left; border-bottom:1px solid #ddd; padding:8px;">名前</th>
                        <th style="text-align:left; border-bottom:1px solid #ddd; padding:8px;">対象日時</th>
                        <th style="text-align:left; border-bottom:1px solid #ddd; padding:8px;">申請理由</th>
                        <th style="text-align:left; border-bottom:1px solid #ddd; padding:8px;">申請日時</th>
                        <th style="text-align:left; border-bottom:1px solid #ddd; padding:8px;">詳細</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $r)
                    @php
                    $u = $r->attendance?->user ?? $r->user;
                    $date = $r->attendance?->work_date
                    ?? ($r->req_clock_in_at?->timezone('Asia/Tokyo')->toDateString()
                    ?? $r->req_clock_out_at?->timezone('Asia/Tokyo')->toDateString());
                    @endphp
                    <tr>
                        <td style="border-bottom:1px solid #eee; padding:8px;">{{ $r->status }}</td>
                        <td style="border-bottom:1px solid #eee; padding:8px;">{{ $u?->name ?? '-' }}</td>
                        <td style="border-bottom:1px solid #eee; padding:8px;">{{ $date ?? '-' }}</td>
                        <td style="border-bottom:1px solid #eee; padding:8px;">{{ $r->reason }}</td>
                        <td style="border-bottom:1px solid #eee; padding:8px;">{{ $r->created_at->timezone('Asia/Tokyo')->format('Y-m-d H:i') }}</td>
                        <td style="border-bottom:1px solid #eee; padding:8px;">
                            <a class="link" href="{{ route('admin.requests.show', $r->id) }}"><strong>詳細</strong></a>
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

        <div style="margin-top:12px;">
            {{ $requests->links() }}
        </div>
    </div>
</div>
@endsection