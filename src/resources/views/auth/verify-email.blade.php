@extends('layouts.app')
@section('title','メール認証が必要です')
@section('body_class','theme-user')

@section('css')
<link rel="stylesheet" href="{{ asset('css/auth-common.css') }}">
@endsection

@section('content')
@php
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

$email = Auth::user()->email ?? '';
$domain = Str::contains($email, '@') ? Str::after($email, '@') : '';

// よく使うドメインはWebメールのURLに誘導（なければnull）
$webmailUrl = match (Str::lower($domain)) {
'gmail.com' => 'https://mail.google.com/',
'yahoo.co.jp' => 'https://mail.yahoo.co.jp/',
'outlook.com',
'hotmail.com',
'live.com' => 'https://outlook.live.com/',
'icloud.com' => 'https://www.icloud.com/mail/',
default => null,
};
@endphp

<div class="auth-container auth-container--wide">
    <div class="auth-card" style="text-align:center;">

        @if (session('status') == 'verification-link-sent')
        <div class="alert alert-info verify-alert">
            新しい認証リンクを送信しました。メールをご確認ください。
        </div>
        @endif

        <p class="notice-hero">
            登録していただいたメールアドレスに認証メールを送付しました。<br>
            メール認証を完了してください。
        </p>

        <div class="stack-16">
            {{-- グレーの「認証はこちら」ボタン（Webメールを新規タブで開く） --}}
            @if ($webmailUrl)
            <a class="btn-gray" href="{{ $webmailUrl }}" target="_blank" rel="noopener">
                認証はこちら
            </a>
            @else
            {{-- ドメインが分からない場合はボタンを無効化して案内だけ表示 --}}
            <button class="btn-gray" type="button" disabled title="ご利用のメールアプリから認証メール内のリンクを開いてください">
                認証はこちら
            </button>
            @endif

            <form method="POST" action="{{ route('verification.send') }}" novalidate>
                @csrf
                <button type="submit" class="btn-link-blue">認証メールを再送する</button>
            </form>

        </div>
    </div>
</div>
@endsection