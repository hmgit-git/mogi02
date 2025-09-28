@extends('layouts.admin')
@section('title','スタッフ一覧')
@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance-common.css') }}">
@endsection

@section('content')
<div class="att-wrap">
    <h1 class="section-title">スタッフ一覧</h1>

    <form method="GET" class="att-filter" action="{{ route('admin.staff.index') }}" role="search" novalidate>
        <input class="input" type="search" name="q"
            value="{{ old('q', $q) }}"
            placeholder="氏名・メールで検索">
        <button class="btn btn-outline" type="submit">検索</button>
    </form>

    <table class="att-table">
        <thead>
            <tr>
                <th>氏名</th>
                <th>メールアドレス</th>
                <th>月次勤怠</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($users as $u)
            <tr>
                <td>{{ $u->name }}</td>
                <td>{{ $u->email }}</td>
                <td>
                    <a class="detail-btn"
                        href="{{ route('admin.staff.attendances', ['user' => $u->id, 'month' => now('Asia/Tokyo')->format('Y-m')]) }}">
                        詳細
                    </a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="3" style="text-align:center; color:#666;">ユーザーが見つかりません</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top:12px;">
        {{ $users->links() }}
    </div>
</div>
@endsection