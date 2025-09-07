@extends('layouts.admin')
@section('title', $dateTitle)
@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance-common.css') }}">
@endsection

@section('content')
<div class="att-wrap">
    <h1 class="section-title">{{ $dateTitle }}の勤怠</h1>

    {{-- 日ナビ：← 前日 | カレンダー+年月日 | 翌日 → --}}
    <form method="GET" action="{{ route('admin.attendances.daily') }}" class="month-bar" novalidate>
        <a class="month-link prev" href="{{ route('admin.attendances.daily', ['date' => $prevDate]) }}">← 前日</a>

        <input type="date" name="date" id="dayPicker" value="{{ $d->toDateString() }}" class="visually-hidden" aria-label="対象日を選択">

        <button type="button" class="month-center" id="dayTrigger" aria-haspopup="dialog" aria-controls="dayPicker">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 2v2H5a2 2 0 0 0-2 2v2h18V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2H7zm14 8H3v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V10z" />
            </svg>
            <strong>{{ $centerYmd }}</strong>
        </button>

        <a class="month-link next" href="{{ route('admin.attendances.daily', ['date' => $nextDate]) }}">翌日 →</a>
    </form>

    <div style="overflow:auto;">
        <table class="att-table">
            <thead>
                <tr>
                    <th>名前</th>
                    <th>出勤</th>
                    <th>退勤</th>
                    <th>休憩</th>
                    <th>合計</th>
                    <th>詳細</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $r)
                <tr>
                    <td>{{ $r['name'] }}</td>
                    <td>{{ $r['clock_in'] }}</td>
                    <td>{{ $r['clock_out'] }}</td>
                    <td>{{ $r['break_hm'] }}</td>
                    <td>{{ $r['work_hm'] }}</td>
                    <td>
                        <a class="detail-btn" href="{{ route('admin.attendances.show', $r['id']) }}">詳細</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center; color:#666;">データがありません</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <script>
        (function() {
            const picker = document.getElementById('dayPicker');
            const trigger = document.getElementById('dayTrigger');
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