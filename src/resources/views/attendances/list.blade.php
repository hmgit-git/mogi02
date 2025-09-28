@extends('layouts.app')
@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance-common.css') }}">
@endsection

@section('title','勤怠一覧')
@section('body_class','theme-user')

@section('content')
<div class="att-wrap">

    {{-- タイトル（左に黒バー） --}}
    <h1 class="section-title">勤怠一覧</h1>

    {{-- 月ナビ：← 前月 | カレンダー+年月 | 翌月 → --}}
    <form id="monthNav" method="GET" action="{{ route('attendance.list') }}" class="month-bar" role="search" novalidate>
        <a class="month-link prev" href="{{ route('attendance.list', ['month' => $prevMonth]) }}" aria-label="前月">← 前月</a>

        {{-- ネイティブmonthは隠して、中央ボタンで開く --}}
        <input type="month" name="month" id="monthPicker" value="{{ $month }}" class="visually-hidden" aria-label="対象月を選択">

        <button type="button" class="month-center" id="monthTrigger" aria-haspopup="dialog" aria-controls="monthPicker">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 2v2H5a2 2 0 0 0-2 2v2h18V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2H7zm14 8H3v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V10z" />
            </svg>
            <span id="monthLabel">{{ $monthLabel }}</span>
        </button>

        <a class="month-link next" href="{{ route('attendance.list', ['month' => $nextMonth]) }}" aria-label="翌月">翌月 →</a>
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
            @foreach ($days as $d)
            <tr>
                {{-- 日付：常にテキスト表示（リンクなし） --}}
                <td>{{ $d['label'] }}</td>

                <td>{{ $d['clock_in'] }}</td>
                <td>{{ $d['clock_out'] }}</td>

                <td>
                    @php $bm = $d['break_min']; @endphp
                    @if ($bm > 0)
                    {{ intdiv($bm,60) }}:{{ $bm % 60 }}
                    @else
                    -
                    @endif
                </td>

                <td>
                    @php $wm = $d['work_min']; @endphp
                    @if ($wm > 0)
                    {{ intdiv($wm,60) }}:{{ $wm % 60 }}
                    @else
                    -
                    @endif
                </td>

                {{-- 詳細：承認待ちがあれば申請詳細へ、なければ従来どおり --}}
                <td>
                    @if (!empty($d['pending_req_id']))
                    {{-- 承認待ち → 申請詳細（編集不可メッセージが出る画面） --}}
                    <a
                        href="{{ route('my.requests.show', $d['pending_req_id']) }}"
                        class="detail-btn"
                        aria-label="{{ $d['label'] }} の申請詳細">詳細</a>
                    @else
                    {{-- 承認待ちなし → 勤怠詳細 or 日付詳細へ --}}
                    <a
                        href="{{ $d['att_id']
                                  ? route('attendance.detail', ['id' => $d['att_id']])
                                  : route('attendance.detail.date', ['date' => $d['date']]) }}"
                        class="detail-btn"
                        aria-label="{{ $d['label'] }} の詳細">詳細</a>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- 月ピッカー制御（ボタン→ネイティブmonthを開く／選択で自動送信） --}}
    <script>
        (function() {
            const picker = document.getElementById('monthPicker');
            const trigger = document.getElementById('monthTrigger');
            const label = document.getElementById('monthLabel');
            const form = document.getElementById('monthNav');

            const fmt = v => v ? v.replace('-', '/') : '';

            trigger.addEventListener('click', () => {
                if (picker.showPicker) picker.showPicker();
                else picker.focus();
            });

            picker.addEventListener('change', () => {
                label.textContent = fmt(picker.value); // YYYY/MM
                form.submit();
            });
        })();
    </script>
    @endsection