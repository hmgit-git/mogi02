@extends('layouts.app')
@section('title','メール認証が必要です')
@section('body_class','theme-user')

@section('content')
<div class="auth-container">
    <div class="auth-card">
        <h1 class="auth-title">メールアドレス確認のお願い</h1>

        @if (session('status') == 'verification-link-sent')
        <div class="alert alert-info">新しい認証リンクを送信しました。メールをご確認ください。</div>
        @endif

        <p style="margin:0 0 12px;">
            登録メールに認証リンクを送信しました。届いていない場合は、以下から再送できます。
        </p>

        <form method="POST" action="{{ route('verification.send') }}" class="auth-form" style="margin-top:8px;">
            @csrf
            <button class="btn btn-primary" type="submit">認証メールを再送</button>
        </form>

        <form method="POST" action="{{ route('logout') }}" style="margin-top:12px;">
            @csrf
            <button class="btn" type="submit">ログアウト</button>
        </form>
    </div>
</div>
@endsection