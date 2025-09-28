@extends('layouts.admin')
@section('title','修正申請一覧')
@section('body_class','theme-admin')
@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance-common.css') }}">
@endsection

@section('content')
<div class="auth-container">
    <div class="auth-card">
        <div class="att-wrap"><!-- 幅を揃える共通ラッパ -->

            <h1 class="auth-title">申請一覧</h1>

            @if (session('status'))
            <div class="alert alert-info">{{ session('status') }}</div>
            @endif

            {{-- タブ（承認待ち / 承認済み） --}}
            @php
            $current = request('status', 'pending'); // 'pending' or 'approved'
            $tz = 'Asia/Tokyo'; // ここが無いとUndefined variable $tz になる
            $statusLabel = fn($s) => match($s) {
            'approved' => '承認済み',
            'rejected' => '否認',
            default => '承認待ち',
            };
            @endphp
            <div class="tabbar-wrap">
                <nav class="tabbar">
                    <a class="tab {{ $current==='pending' ? 'is-active' : '' }}"
                        href="{{ route('admin.requests.index', ['status' => 'pending']) }}">承認待ち</a>
                    <a class="tab {{ $current==='approved' ? 'is-active' : '' }}"
                        href="{{ route('admin.requests.index', ['status' => 'approved']) }}">承認済み</a>
                </nav>
            </div>

            <div class="table-wrap">
                <table class="att-table">
                    <thead>
                        <tr>
                            <th>状態</th>
                            <th>名前</th>
                            <th>対象日時</th>
                            <th>申請理由</th>
                            <th>申請日時</th>
                            <th>詳細</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requests as $r)
                        @php
                        $u = $r->attendance?->user ?? $r->user;
                        // 対象日時：work_date 優先 → req_clock_in_at / req_clock_out_at のどれか
                        $rawTarget = $r->attendance?->work_date
                        ?? ($r->req_clock_in_at ?: $r->req_clock_out_at);
                        $targetLabel = $rawTarget
                        ? \Illuminate\Support\Carbon::parse($rawTarget)->timezone($tz)->format('Y/m/d')
                        : '-';
                        @endphp
                        <tr>
                            <td>{{ $statusLabel($r->status) }}</td>
                            <td>{{ $u?->name ?? '-' }}</td>
                            <td>{{ $targetLabel }}</td>
                            <td class="col-reason">{{ \Illuminate\Support\Str::limit($r->reason, 60) }}</td>
                            <td>{{ $r->created_at?->timezone($tz)?->format('Y/m/d') ?? '-' }}</td>
                            <td><a class="detail-btn" href="{{ route('admin.requests.show', $r->id) }}">詳細</a></td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">データがありません</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pager">
                {{ $requests->links() }}
            </div>

        </div><!-- /att-wrap -->
    </div>
</div>
@endsection