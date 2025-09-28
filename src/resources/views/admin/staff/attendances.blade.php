@extends('layouts.admin')
@section('title', $staff->name . ' さんの月次勤怠')
@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance-common.css') }}">
@endsection

@section('content')
<div class="att-wrap">
    <h1 class="section-title">{{ $staff->name }} さんの月次勤怠</h1>

    {{-- 月ナビ --}}
    <form method="GET" action="{{ route('admin.staff.attendances', ['user' => $staff->id]) }}" class="month-bar" novalidate>
        <a class="month-link prev"
            href="{{ route('admin.staff.attendances', ['user' => $staff->id, 'month' => $prevMonth]) }}">← 前月</a>

        <input type="month" name="month" id="monthPicker" value="{{ $month }}" class="visually-hidden">

        <button type="button" class="month-center" id="monthTrigger">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 2v2H5a2 2 0 0 0-2 2v2h18V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2H7zm14 8H3v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V10z" />
            </svg>
            <strong>{{ $monthLabel }}</strong>
        </button>

        <a class="month-link next"
            href="{{ route('admin.staff.attendances', ['user' => $staff->id, 'month' => $nextMonth]) }}">翌月 →</a>
    </form>

    <table class="att-table">
        <thead>
            <tr>
                <th>日付</th>
                <th>出勤</th>
                <th>退勤</th>
                <th>休憩</th>
                <th>合計</th>
                <th>詳細</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
            <tr>
                <td>{{ $r['label'] }}</td>
                <td>{{ $r['clock_in']  ?: '-' }}</td>
                <td>{{ $r['clock_out'] ?: '-' }}</td>
                <td>
                    @php $bm = $r['break_min']; @endphp
                    @if ($bm > 0)
                    {{ intdiv($bm,60) }}:{{ $bm % 60 }}
                    @else
                    -
                    @endif
                </td>
                <td>
                    @php $wm = $r['work_min']; @endphp
                    @if ($wm > 0)
                    {{ intdiv($wm,60) }}:{{ $wm % 60 }}
                    @else
                    -
                    @endif
                </td>
                <td>
                    @if ($r['att_id'])
                    <a class="detail-btn" href="{{ route('admin.attendances.show', $r['att_id']) }}">詳細</a>
                    @else
                    <span class="detail-btn is-disabled">詳細</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- 右下のCSV出力（黒ボタン） --}}
    {{-- テーブルの直後に配置 --}}
    <div class="csv-export">
        <a class="btn-csv"
            href="{{ route('admin.staff.attendances.csv', ['user' => $staff->id, 'month' => $month]) }}">
            CSV出力
        </a>
    </div>

    <script>
        (function() {
            const picker = document.getElementById('monthPicker');
            const trigger = document.getElementById('monthTrigger');
            trigger.addEventListener('click', () => {
                if (picker.showPicker) picker.showPicker();
                else picker.focus();
            });
            picker.addEventListener('change', () => {
                picker.form.submit();
            });
        })();
    </script>
</div>
@endsection