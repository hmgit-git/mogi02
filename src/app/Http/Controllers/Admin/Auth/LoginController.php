<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;


class LoginController extends Controller
{
    public function showLoginForm()
    {
        if (auth('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.auth.login');
    }

    public function login(AdminLoginRequest $request) 
    {
        $credentials = $request->only('email', 'password');
        $remember = (bool) $request->boolean('remember');

        if (Auth::guard('admin')->attempt(array_merge($credentials, ['role' => 'admin']), $remember)) {
            $request->session()->regenerate();
            return redirect()->intended(route('admin.dashboard'));
        }

        // 要件の失敗メッセージに統一
        throw ValidationException::withMessages([
            'email' => 'ログイン情報が登録されていません',
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
