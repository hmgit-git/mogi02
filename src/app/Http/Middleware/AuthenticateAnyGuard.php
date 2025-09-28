<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class AuthenticateAnyGuard
{
    public function handle($request, Closure $next)
    {
        // 管理者ログイン済み？
        if (Auth::guard('admin')->check()) {
            $request->attributes->set('active_guard', 'admin');
            return $next($request);
        }

        // 一般ユーザーログイン済み？
        if (Auth::guard('web')->check()) {
            $request->attributes->set('active_guard', 'web');
            return $next($request);
        }

        // どちらも未ログインなら、一般ログインへ飛ばす（必要なら admin/login に分岐してもOK）
        return redirect()->guest('/login');
    }
}
