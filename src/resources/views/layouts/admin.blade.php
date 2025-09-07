<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '管理画面')</title>

    {{-- 共通フォント・CSS --}}
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @yield('css')
</head>

<body>
    <header class="site-header">
        <div class="site-header__inner">
            <a href="{{ route('admin.dashboard') }}" class="site-header__brand">
                <img src="{{ asset('images/logo.svg') }}" alt="ロゴ" class="site-header__logo">
            </a>

            <nav class="site-header__nav">

                <a href="{{ route('admin.attendances.daily') }}" class="nav-btn">勤怠一覧</a>
                <a href="{{ route('admin.staff.index') }}" class="nav-btn">スタッフ一覧</a>
                <a href="{{ route('admin.requests.index') }}" class="nav-btn">申請一覧</a>
                <form method="POST" action="{{ route('admin.logout') }}" class="nav-logout" novalidate>
                    @csrf
                    <button type="submit" class="nav-btn">
                        ログアウト
                    </button>
                </form>

            </nav>
        </div>
    </header>

    <main class="site-main">
        @yield('content')
    </main>

    <footer class="site-footer">
        <p>&copy; {{ date('Y') }} 管理画面</p>
    </footer>
</body>

</html>