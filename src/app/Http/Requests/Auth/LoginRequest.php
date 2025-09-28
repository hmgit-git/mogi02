<?php

namespace App\Http\Requests\Auth;

use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FortifyLoginRequest
{
    public function rules(): array
    {
        return [
            'email'    => 'required|email',
            'password' => 'required',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'    => ':attributeを入力してください',
            'email.email'       => ':attributeの形式が正しくありません。',
            'password.required' => ':attributeを入力してください',
        ];
    }

    public function attributes(): array
    {
        return [
            'email'    => 'メールアドレス',
            'password' => 'パスワード',
        ];
    }

    /**
     * 一般ログインは「role = user」のみ許可。
     * admin などはここでは弾いて常に同じ失敗メッセージにする。
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $ok = Auth::guard(config('fortify.guard', 'web'))->attempt([
            'email'    => $this->input('email'),
            'password' => $this->input('password'),
            'role'     => 'user', 
        ], $this->boolean('remember'));

        if (! $ok) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'ログイン情報が登録されていません',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }
}
