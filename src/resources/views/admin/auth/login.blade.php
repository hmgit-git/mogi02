@extends('layouts.app')
@section('title','管理者ログイン')
@section('body_class','theme-admin')

@section('content')
<div class="auth-container">
    <div class="auth-card">
        <h1 class="auth-title">管理者ログイン</h1>

        @if ($errors->any())
        <div class="alert alert-error">
            <ul class="error-list">
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- ルート名は環境に合わせて（例：POST /admin/login） --}}
        <form method="POST" action="{{ route('admin.login') }}" class="auth-form" novalidate>
            @csrf

            <label class="auth-label" for="email">メールアドレス</label>
            <input id="email" class="input" type="email" name="email" value="{{ old('email') }}" required autofocus>

            <label class="auth-label" for="password">パスワード</label>
            <input id="password" class="input" type="password" name="password" required>

            <label class="checkbox-wrap">
                <input type="checkbox" name="remember" value="1"> ログイン状態を保持する
            </label>

            <button class="btn btn-primary" type="submit">ログイン</button>
        </form>
    </div>
</div>
@endsection