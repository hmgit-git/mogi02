<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', '勤怠管理')</title>

    {{-- フォント --}}
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

    {{-- 共通CSS --}}
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/auth-common.css') }}">
    @yield('css')
</head>

<body class="@yield('body_class')">
    <header class="site-header">
        <div class="site-header__inner">
            {{-- 左側：ロゴ --}}
            <a href="/" class="site-header__brand" aria-label="ホームへ">
                <img src="{{ asset('images/logo.svg') }}" alt="ロゴ" class="site-header__logo">
            </a>

            <nav class="site-header__nav">
                @auth
                <a href="{{ route('attendance.index') }}" class="nav-btn">勤怠</a>
                <a href="{{ route('attendance.list') }}" class="nav-btn">勤怠一覧</a>
                <a href="#" class="nav-btn" onclick="event.preventDefault(); alert('申請ページは準備中です');">申請</a>
                <form method="POST" action="{{ route('logout') }}" class="nav-logout" novalidate>
                    @csrf
                    <button type="submit" class="nav-btn">ログアウト</button>
                </form>
                @endauth
            </nav>

        </div>
    </header>

    <main class="site-main">
        @yield('content')
    </main>

    <footer class="site-footer">
        <p>&copy; {{ date('Y') }} 勤怠管理</p>
    </footer>
</body>

</html>