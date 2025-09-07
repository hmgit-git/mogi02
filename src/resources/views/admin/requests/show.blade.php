@extends('layouts.admin')
@section('title','勤怠詳細（申請）')
@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance-common.css') }}">
@endsection
@section('body_class','theme-admin')

@section('content')
@php
$tz = 'Asia/Tokyo';
$changes = $req->requested_changes ?? [];

// HH:mm 表示フォーマッタ
$fmt = function($v) use ($tz){
if(!$v) return '-';
if(preg_match('/^\d{1,2}:\d{2}$/',$v)) return $v;
try { return \Illuminate\Support\Carbon::parse($v,$tz)->format('H:i'); }
catch (\Throwable $e) { return '-'; }
};

$userName = $req->applicant?->name ?? ($req->attendance?->user?->name ?? '-');

// 見出し用：年・月日
$targetDateStr = $req->attendance?->work_date
?? ($changes['clock_in_at'] ?? $changes['clock_out_at'] ?? null);
if ($targetDateStr) {
$dObj = \Illuminate\Support\Carbon::parse($targetDateStr, $tz);
$yearStr = $dObj->year . '年';
$mdStr = $dObj->format('n月j日');
} else {
$yearStr = $mdStr = '';
}

// 申請の表示
$inDisp = $fmt($changes['clock_in_at'] ?? null);
$outDisp = $fmt($changes['clock_out_at'] ?? null);
$breaks = (is_array($changes['breaks'] ?? null)) ? $changes['breaks'] : [];

// 現在の勤怠値（参考表示）
$inNow = $req->attendance?->clock_in_at ? $req->attendance->clock_in_at->timezone($tz)->format('H:i') : null;
$outNow = $req->attendance?->clock_out_at ? $req->attendance->clock_out_at->timezone($tz)->format('H:i') : null;
@endphp

<div class="att-wrap">
    <h1 class="section-title">申請詳細</h1>

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

    <table class="att-table">
        <tbody>
            <tr>
                <th>名前</th>
                <td>
                    <div class="input input-static">{{ $userName }}</div>
                </td>
            </tr>

            <tr>
                <th>日付</th>
                <td>
                    <div class="triple">
                        <div class="input input-static">{{ $yearStr }}</div>
                        <span class="triple-sep"></span>
                        <div class="input input-static">{{ $mdStr }}</div>
                    </div>
                </td>
            </tr>

            <tr>
                <th>出勤・退勤</th>
                <td>
                    <div class="triple">
                        <div class="input input-static">{{ $inDisp }}</div>
                        <span class="triple-sep">〜</span>
                        <div class="input input-static">{{ $outDisp }}</div>
                    </div>
                    @if($inNow || $outNow)
                    <div class="meta-note">（現在：{{ $inNow ?: '--:--' }} 〜 {{ $outNow ?: '--:--' }}）</div>
                    @endif
                </td>
            </tr>

            {{-- 休憩：ゼロ件なら一切表示しない --}}
            @foreach($breaks as $i => $b)
            @php
            $s = $fmt($b['start_at'] ?? null);
            $e = $fmt($b['end_at'] ?? null);
            @endphp
            <tr>
                <th>{{ $i===0 ? '休憩' : '休憩'.($i+1) }}</th>
                <td>
                    <div class="triple">
                        <div class="input input-static">{{ $s }}</div>
                        <span class="triple-sep">〜</span>
                        <div class="input input-static">{{ $e }}</div>
                    </div>
                </td>
            </tr>
            @endforeach

            <tr>
                <th>備考</th>
                <td>
                    <div class="input-static-note">{{ $req->reason ?: '（なし）' }}</div>
                </td>
            </tr>
        </tbody>
    </table>

    {{-- 右下：承認（pending のときだけ） --}}
    @if($req->isPending())
    <div class="form-actions-right">
        <form id="approveForm"
            action="{{ route('admin.requests.approve', $req->id) }}"
            method="POST"
            novalidate>
            @csrf
            <button id="approveBtn" class="btn-submit" type="submit">承認</button>
        </form>
    </div>
    @else
    <div class="form-actions-right">
        <span class="btn-submit is-done" aria-disabled="true">承認済み</span>
    </div>
    @endif
</div>

{{-- 承認 AJAX（遷移なしでボタンを「承認済み」に） --}}
<script>
    (function() {
        const form = document.getElementById('approveForm');
        if (!form) return;

        const btn = document.getElementById('approveBtn');
        const url = form.action;

        // layout に <meta name="csrf-token" content="{{ csrf_token() }}"> があることが前提
        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        const token = tokenMeta ? tokenMeta.getAttribute('content') : '';

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!btn || btn.dataset.loading === '1') return;

            btn.dataset.loading = '1';
            btn.disabled = true;

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: new FormData(form)
                });

                if (!res.ok) {
                    // バリデーション422など
                    let msg = '承認処理に失敗しました。';
                    try {
                        const j = await res.json();
                        if (j && j.errors) msg = (Array.isArray(j.errors) ? j.errors.join(' / ') : '承認処理に失敗しました。');
                    } catch (_) {}
                    alert(msg);
                    btn.disabled = false;
                    return;
                }

                const json = await res.json();
                if (json && json.ok && json.status === 'approved') {
                    // ボタンを「承認済み」表示に差し替え
                    btn.textContent = '承認済み';
                    btn.classList.add('is-done');
                    btn.setAttribute('aria-disabled', 'true');
                } else {
                    // 想定外はリロードで同期
                    location.reload();
                }
            } catch (err) {
                console.error(err);
                alert('承認処理に失敗しました。通信状況をご確認ください。');
                btn.disabled = false;
            } finally {
                btn.dataset.loading = '0';
            }
        });
    })();
</script>
@endsection