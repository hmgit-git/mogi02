@extends('layouts.app')
@section('title', 'ログイン')
@section('body_class', 'theme-user')

@section('content')
<div class="auth-container">
    <div class="auth-card">
        <h1 class="auth-title">ログイン</h1>

        @if (session('status'))
        <div class="alert alert-info">{{ session('status') }}</div>
        @endif
        @if (session('auth_error'))
        <div class="alert alert-error">{{ session('auth_error') }}</div>
        @endif
        @if ($errors->any())
        <div class="alert alert-error">
            <ul class="error-list">
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="auth-form" novalidate>
            @csrf

            <label class="auth-label" for="email">メールアドレス</label>
            <input id="email" class="input" type="email" name="email" value="{{ old('email') }}" required autofocus>

            <label class="auth-label" for="password">パスワード</label>
            <input id="password" class="input" type="password" name="password" required>

            <button class="btn btn-primary" type="submit">ログイン</button>

            <div class="actions">
                <a class="link" href="{{ route('register') }}">会員登録はこちら</a>
                <a class="link" href="{{ route('password.request') }}">パスワードをお忘れですか？</a>
            </div>
        </form>
    </div>
</div>
@endsection