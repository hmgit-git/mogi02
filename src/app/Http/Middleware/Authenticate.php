<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    protected function redirectTo($request): ?string
    {
        if (!$request->expectsJson()) {
            // 管理者ルートなら /admin/login へ
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.login.form');
            }
            // それ以外は通常ログインへ（Fortify等）
            return route('login');
        }
        return null;
    }
}
