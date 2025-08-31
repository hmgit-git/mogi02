@extends('layouts.admin')
@section('title','勤怠詳細（申請）')
@section('body_class','theme-admin')

@section('content')
@php
use Illuminate\Support\Carbon;
$youbi = fn($d) => ['日','月','火','水','木','金','土'][Carbon::parse($d,'Asia/Tokyo')->dayOfWeek];
$att = $req->attendance;
$user = $att?->user ?? $req->user;
$dateLabel = $workDate ? Carbon::parse($workDate,'Asia/Tokyo')->format('Y年n月j日') . '(' . $youbi($workDate) . ')' : '-';
@endphp

<div class="auth-container">
    <div class="auth-card">
        <h1 class="auth-title">勤怠詳細</h1>

        <div style="display:grid; gap:12px;">
            <div><strong>名前：</strong>{{ $user?->name ?? '-' }}</div>
            <div><strong>日付：</strong>{{ $dateLabel }}</div>

            <div><strong>出勤：</strong>
                @if($req->req_clock_in_at) 申請 {{ $req->req_clock_in_at->timezone('Asia/Tokyo')->format('H:i') }} @endif
                @if($att?->clock_in_at) ／ 現在 {{ $att->clock_in_at->timezone('Asia/Tokyo')->format('H:i') }} @endif
            </div>

            <div><strong>退勤：</strong>
                @if($req->req_clock_out_at) 申請 {{ $req->req_clock_out_at->timezone('Asia/Tokyo')->format('H:i') }} @endif
                @if($att?->clock_out_at) ／ 現在 {{ $att->clock_out_at->timezone('Asia/Tokyo')->format('H:i') }} @endif
            </div>

            <div>
                <strong>休憩：</strong>
                <div style="margin-top:6px;">
                    @if(is_array($req->requested_breaks) && count($req->requested_breaks))
                    @foreach($req->requested_breaks as $i => $b)
                    <div>休憩{{ $i+1 }}：{{ isset($b['start_at']) ? \Illuminate\Support\Carbon::parse($b['start_at'],'Asia/Tokyo')->format('H:i') : '-' }}
                        〜 {{ isset($b['end_at']) ? \Illuminate\Support\Carbon::parse($b['end_at'],'Asia/Tokyo')->format('H:i') : '-' }}</div>
                    @endforeach
                    @else
                    <div>（休憩申請なし）</div>
                    @endif
                </div>

                @if($att && $att->breaks->count())
                <div style="margin-top:8px; color:#666; font-size:13px;">
                    現在：@foreach($att->breaks as $i => $br)[{{ $br->start_at?->timezone('Asia/Tokyo')->format('H:i') }}-{{ $br->end_at?->timezone('Asia/Tokyo')->format('H:i') ?? '--:--' }}] @endforeach
                </div>
                @endif
            </div>

            <div><strong>備考：</strong>{{ $req->reason ?: '（なし）' }}</div>
        </div>

        {{-- 右下：承認ボタン（pendingのときのみ） --}}
        @if($req->isPending())
        <div style="display:flex; justify-content:flex-end; margin-top:20px;">
            <form method="POST" action="{{ route('admin.requests.approve', $req->id) }}">
                @csrf
                <button class="btn btn-primary" type="submit" style="width:200px; height:48px;">承認</button>
            </form>
        </div>
        @else
        <div class="alert alert-info" style="margin-top:16px;">
            この申請は {{ $req->status }} です（{{ optional($req->reviewed_at)->timezone('Asia/Tokyo')->format('Y-m-d H:i') }} / {{ $req->reviewer?->name }}）。
        </div>
        @endif
    </div>
</div>
@endsection