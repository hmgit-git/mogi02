@extends('layouts.app')
@section('title', '管理者ダッシュボード')

@section('content')
<div class="auth-container">
    <div class="auth-card">
        <h1 class="auth-title" style="margin-bottom:12px;">管理者ダッシュボード</h1>

        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button class="btn btn-primary" type="submit">ログアウト</button>
        </form>
    </div>
</div>
@endsection