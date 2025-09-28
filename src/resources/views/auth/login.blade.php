@extends('layouts.app')
@section('title', 'ログイン')
@section('body_class', 'theme-user')
@section('css')
<link rel="stylesheet" href="{{ asset('css/auth-common.css') }}">
@endsection

@section('content')
<div class="auth-container">
    <div class="auth-card">
        <h1 class="auth-title">ログイン</h1>

        <form method="POST" action="{{ route('login') }}" class="auth-form" novalidate>
            @csrf

            {{-- メールアドレス --}}
            <label class="auth-label" for="email">メールアドレス</label>
            <input id="email"
                class="input @error('email') is-invalid @enderror"
                type="email" name="email" value="{{ old('email') }}" required>
            @error('email') <p class="field-error">{{ $message }}</p> @enderror

            {{-- パスワード --}}
            <label class="auth-label" for="password">パスワード</label>
            <input id="password"
                class="input @error('password') is-invalid @enderror"
                type="password" name="password" required>
            @error('password') <p class="field-error">{{ $message }}</p> @enderror

            <button class="btn btn-primary" type="submit">ログイン</button>

            <div class="actions">
                <a class="link" href="{{ route('register') }}">会員登録はこちら</a>
            </div>
        </form>
    </div>
</div>
@endsection