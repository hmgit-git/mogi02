<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;
use App\Http\Requests\Auth\LoginRequest as AppLoginRequest;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\ResetUserPassword;

use Illuminate\Validation\ValidationException;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Fortify の LoginRequest を自作 FormRequest に差し替え
        $this->app->bind(FortifyLoginRequest::class, AppLoginRequest::class);
    }

    public function boot(): void
    {
        RateLimiter::for('login', function () {
            return Limit::none(); // 制限なし
        });
        // ビュー
        Fortify::loginView(fn() => view('auth.login'));
        Fortify::registerView(fn() => view('auth.register'));
        Fortify::verifyEmailView(fn() => view('auth.verify-email'));
        // Fortify アクション
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // カスタム認証（メール未登録のメッセージ）

        Fortify::authenticateUsing(function (\Illuminate\Http\Request $request) {
            $user = \App\Models\User::where('email', $request->email)->first();

            if ($user && \Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
                return $user;
            }

            throw ValidationException::withMessages([
                'email' => ['ログイン情報が登録されていません'],
            ]);
        });
    }
}
