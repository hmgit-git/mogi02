<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('admin.auth.login');
    }

    public function login(AdminLoginRequest $request)
    {
        $ok = Auth::guard('admin')->attempt($request->only('email', 'password'), $request->boolean('remember'));
        if ($ok) {
            $request->session()->regenerate();
            return redirect()->intended(route('admin.dashboard'));
        }
        throw ValidationException::withMessages(['email' => ['ログイン情報が登録されていません']]);
    }

    public function logout()
    {
        Auth::guard('admin')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect()->route('admin.login.form');
    }
}
