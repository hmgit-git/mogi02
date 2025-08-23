@extends('layouts.app')
@section('title', '会員登録')
@section('body_class', 'theme-user')

@section('content')
<div class="auth-container">
    <div class="auth-card">
        <h1 class="auth-title">会員登録</h1>

        @if ($errors->any())
        <div class="alert alert-error">
            <ul class="error-list">
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('register') }}" class="auth-form" novalidate>
            @csrf

            <label class="auth-label" for="name">名前</label>
            <input id="name" class="input" type="text" name="name" value="{{ old('name') }}" required>

            <label class="auth-label" for="email">メールアドレス</label>
            <input id="email" class="input" type="email" name="email" value="{{ old('email') }}" required>

            <label class="auth-label" for="password">パスワード</label>
            <input id="password" class="input" type="password" name="password" required minlength="8">

            <label class="auth-label" for="password_confirmation">パスワード（確認）</label>
            <input id="password_confirmation" class="input" type="password" name="password_confirmation" required minlength="8">

            <button class="btn btn-primary" type="submit">登録</button>

            <div class="actions">
                <a class="link" href="{{ route('login') }}">ログインはこちら</a>
            </div>
        </form>
    </div>
</div>
@endsection