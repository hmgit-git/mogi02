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

            <button class="site-header__menu-toggle" id="menuToggle"
                aria-label="メニューを開閉" aria-expanded="false" aria-controls="siteHeaderNav">
                <span class="bar"></span>
            </button>

            <nav class="site-header__nav" id="siteHeaderNav">

                @auth
                <a href="{{ route('attendance.index') }}" class="nav-btn">勤怠</a>
                <a href="{{ route('attendance.list') }}" class="nav-btn">勤怠一覧</a>

                <a href="{{ route('my.requests.pending') }}" class="nav-btn">申請</a>

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
    <script>
        (function() {
            const header = document.querySelector('.site-header');
            const toggle = document.getElementById('menuToggle');
            const nav = document.getElementById('siteHeaderNav');

            if (!toggle || !header || !nav) return;

            // 開閉トグル
            toggle.addEventListener('click', () => {
                const opened = header.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', opened ? 'true' : 'false');
            });

            // ナビ内クリックで自動クローズ（遷移時の開きっぱなし防止）
            nav.addEventListener('click', (e) => {
                const target = e.target;
                if (target.closest('a') || target.closest('button[type="submit"]')) {
                    header.classList.remove('is-open');
                    toggle.setAttribute('aria-expanded', 'false');
                }
            });

            // 画面幅が戻ったら閉じる（レイアウト切替の取りこぼし対策）
            let mq = window.matchMedia('(min-width: 769px)');
            mq.addEventListener('change', (ev) => {
                if (ev.matches) {
                    header.classList.remove('is-open');
                    toggle.setAttribute('aria-expanded', 'false');
                }
            });
        })();
    </script>

    <footer class="site-footer">
        <p>&copy; {{ date('Y') }} 勤怠管理</p>
    </footer>
</body>

</html>