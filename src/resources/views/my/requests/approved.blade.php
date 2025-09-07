@extends('layouts.app')
@section('title','申請一覧（承認済み）')

@section('content')
<h1 class="section-title">申請一覧（承認済み）</h1>

@if($rows->isEmpty())
<p>承認済みの申請はありません。</p>
@else
<div style="overflow:auto;">
    <table class="att-table" style="width:100%; border-collapse:collapse;">
        <thead>
            <tr>
                <th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">承認日時</th>
                <th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">対象日</th>
                <th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">反映された出勤 → 退勤</th>
                <th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">備考</th>
                <th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">詳細</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $r)
            @php
            $tz = 'Asia/Tokyo';
            $changes = $r->requested_changes ?? [];
            $target = $r->attendance?->work_date ?? ($changes['clock_in_at'] ?? $changes['clock_out_at'] ?? null);
            $targetDate = $target ? \Illuminate\Support\Carbon::parse($target, $tz)->toDateString() : '-';
            @endphp
            <tr>
                <td style="padding:8px; border-bottom:1px solid #eee;">
                    {{ optional($r->reviewed_at)->timezone($tz)?->format('Y-m-d H:i') ?? '-' }}
                </td>
                <td style="padding:8px; border-bottom:1px solid #eee;">{{ $targetDate }}</td>
                <td style="padding:8px; border-bottom:1px solid #eee;">
                    @if($r->attendance)
                    {{ optional($r->attendance->clock_in_at)->timezone($tz)?->format('Y-m-d H:i') ?? '-' }}
                    →
                    {{ optional($r->attendance->clock_out_at)->timezone($tz)?->format('Y-m-d H:i') ?? '-' }}
                    @else
                    -
                    @endif
                </td>
                <td style="padding:8px; border-bottom:1px solid #eee; max-width:28rem;">
                    {{ \Illuminate\Support\Str::limit($r->reason, 60) }}
                </td>
                <td style="padding:8px; border-bottom:1px solid #eee;">
                    <a class="link" href="{{ route('my.requests.show', $r->id) }}">詳細</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div style="margin-top:12px;">
    {{ $rows->links() }}
</div>
@endif
@endsection