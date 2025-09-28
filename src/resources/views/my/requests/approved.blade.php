@extends('layouts.app')
@section('title','申請一覧')
@section('body_class','theme-user') {{-- ★ 追加：ユーザー用スコープ --}}
@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance-common.css') }}">
@endsection


@section('content')
<div class="att-wrap">
    <h1 class="section-title">申請一覧</h1>

    @php
    $current = 'approved';
    $tz = 'Asia/Tokyo';
    $statusLabel = fn($s) => match($s){
    'approved' => '承認済み',
    'rejected' => '否認',
    default => '承認待ち',
    };
    @endphp

    <div class="tabbar-wrap">
        <nav class="tabbar">
            <a class="tab" href="{{ route('my.requests.pending') }}">承認待ち</a>
            <a class="tab is-active" href="{{ route('my.requests.approved') }}">承認済み</a>
        </nav>
    </div>

    @if($rows->isEmpty())
    <p>承認済みの申請はありません。</p>
    @else
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
                @foreach($rows as $r)
                @php
                $changes = $r->requested_changes ?? [];
                $target = $r->attendance?->work_date ?? ($changes['clock_in_at'] ?? $changes['clock_out_at'] ?? null);
                $targetLabel = $target ? \Illuminate\Support\Carbon::parse($target)->timezone($tz)->format('Y/m/d') : '-';
                $name = $r->attendance?->user?->name ?? ($r->applicant?->name ?? (auth()->user()->name ?? '-'));
                @endphp
                <tr>
                    <td>{{ $statusLabel($r->status ?? 'approved') }}</td>
                    <td>{{ $name }}</td>
                    <td>{{ $targetLabel }}</td>
                    <td class="col-reason">{{ \Illuminate\Support\Str::limit($r->reason, 60) }}</td>
                    <td>{{ $r->created_at?->timezone($tz)?->format('Y/m/d') ?? '-' }}</td>
                    <td><a class="detail-btn" href="{{ route('my.requests.show', $r->id) }}">詳細</a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="pager">
        {{ $rows->links() }}
    </div>
    @endif
</div>
@endsection