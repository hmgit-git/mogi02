<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            // admin配下は管理者ログインへ（/admin 単体も拾う）
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.login.form');
            }
            // それ以外は通常ログインへ
            return route('login');
        }

        // ここは何も返さない（nullでOK）
    }
}
