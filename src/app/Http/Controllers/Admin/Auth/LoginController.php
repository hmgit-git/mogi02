<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;


class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        // バリデーション
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        // role=admin を必須条件にして attempt（同じ users 表を使う）
        $remember = (bool) $request->boolean('remember');

        // セッション固定攻撃対策：成功時にID再生成
        if (Auth::guard('admin')->attempt(
            array_merge($credentials, ['role' => 'admin']),
            $remember
        )) {
            $request->session()->regenerate();
            return redirect()->intended(route('admin.dashboard'));
        }

        // 失敗時
        throw ValidationException::withMessages([
            'email' => 'メールアドレスまたはパスワードが正しくありません。（または管理者権限がありません）',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login.form');
    }
}
