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

<body class="@yield('body_class', 'theme-admin')">
    <header class="site-header">
        <div class="site-header__inner">
            <a href="{{ route('admin.dashboard') }}" class="site-header__brand">
                <img src="{{ asset('images/logo.svg') }}" alt="ロゴ" class="site-header__logo">
            </a>

            <button class="site-header__menu-toggle" id="adminMenuToggle"
                aria-label="メニューを開閉" aria-expanded="false" aria-controls="adminHeaderNav">
                <span class="bar"></span>
            </button>

            <nav class="site-header__nav" id="adminHeaderNav">

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
    <script>
        (function() {
            const header = document.querySelector('.site-header');
            const toggle = document.getElementById('adminMenuToggle');
            const nav = document.getElementById('adminHeaderNav');
            if (!toggle || !header || !nav) return;

            // 開閉
            toggle.addEventListener('click', () => {
                const opened = header.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', opened ? 'true' : 'false');
            });

            // ナビクリックで自動クローズ（遷移時の開きっぱなし防止）
            nav.addEventListener('click', (e) => {
                if (e.target.closest('a') || e.target.closest('button[type="submit"]')) {
                    header.classList.remove('is-open');
                    toggle.setAttribute('aria-expanded', 'false');
                }
            });

            // 画面幅が戻ったら閉じる
            const mq = window.matchMedia('(min-width: 769px)');
            mq.addEventListener('change', (ev) => {
                if (ev.matches) {
                    header.classList.remove('is-open');
                    toggle.setAttribute('aria-expanded', 'false');
                }
            });
        })();
    </script>

    <footer class="site-footer">
        <p>&copy; {{ date('Y') }} 管理画面</p>
    </footer>
</body>

</html>