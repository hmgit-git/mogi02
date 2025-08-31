@extends('layouts.app')
@section('title','管理者ログイン')
@section('body_class','theme-admin')

@section('content')
<div class="auth-container">
    <div class="auth-card">
        <h1 class="auth-title">管理者ログイン</h1>

        {{-- 認証失敗（ログイン情報が登録されていません）専用 --}}
        @error('auth')
        <div class="alert alert-error" style="margin-bottom:12px;">
            {{ $message }}
        </div>
        @enderror

        <form method="POST" action="{{ route('admin.login') }}" class="auth-form" novalidate>
            @csrf

            <label class="auth-label" for="email">メールアドレス</label>
            <input id="email" class="input" type="email" name="email" value="{{ old('email') }}" required autofocus>
            @error('email')
            <div class="field-error">{{ $message }}</div>
            @enderror

            <label class="auth-label" for="password">パスワード</label>
            <input id="password" class="input" type="password" name="password" required>
            @error('password')
            <div class="field-error">{{ $message }}</div>
            @enderror

            <button class="btn btn-primary" type="submit">ログイン</button>
        </form>
    </div>
</div>
@endsection