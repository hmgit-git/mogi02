@extends('layouts.app')
@section('title', '会員登録')
@section('body_class', 'theme-user')
@section('css')
<link rel="stylesheet" href="{{ asset('css/auth-common.css') }}">
@endsection

@section('content')
<div class="auth-container">
    <div class="auth-card">
        <h1 class="auth-title">会員登録</h1>

        <form method="POST" action="{{ route('register') }}" class="auth-form" novalidate>
            @csrf

            {{-- 名前 --}}
            <label class="auth-label" for="name">名前</label>
            <input id="name"
                class="input @error('name') is-invalid @enderror"
                type="text" name="name" value="{{ old('name') }}" required>
            @error('name') <p class="field-error">{{ $message }}</p> @enderror

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
                type="password" name="password" required minlength="8">
            {{-- ここでは「不一致」メッセージは出さない --}}
            @error('password')
            @if($message !== 'パスワードと一致しません')
            <p class="field-error">{{ $message }}</p>
            @endif
            @enderror

            {{-- パスワード（確認） --}}
            <label class="auth-label" for="password_confirmation">パスワード（確認）</label>
            <input id="password_confirmation"
                class="input @error('password_confirmation') is-invalid @enderror"
                type="password" name="password_confirmation" required minlength="8">
            @error('password_confirmation') <p class="field-error">{{ $message }}</p> @enderror

            {{-- 「パスワードと一致しません」は確認欄の下に表示させる --}}
            @if($errors->has('password') && $errors->first('password') === 'パスワードと一致しません')
            <p class="field-error">パスワードと一致しません</p>
            @endif

            <button class="btn btn-primary" type="submit">登録</button>

            <div class="actions">
                <a class="link" href="{{ route('login') }}">ログインはこちら</a>
            </div>
        </form>
    </div>
</div>
@endsection