<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Requests\Auth\LoginRequest as AppLoginRequest;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Fortify の LoginRequest を自作 FormRequest に差し替え
        $this->app->bind(FortifyLoginRequest::class, AppLoginRequest::class);
    }

    public function boot(): void
    {
        // （必要に応じて調整）ログインのレート制限
        RateLimiter::for('login', function () {
            return Limit::none();
        });

        // 認証関連ビュー
        Fortify::loginView(fn() => view('auth.login'));
        Fortify::registerView(fn() => view('auth.register'));
        Fortify::verifyEmailView(fn() => view('auth.verify-email'));

        // Fortify アクション
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        /**
         * カスタム認証ロジック
         * - まず通常の資格情報チェック
         * - その上で「一般ログイン(/login)からadminは弾く」
         *   ※ 管理者は /admin/login（独自コントローラ＋adminガード）から入る想定
         */
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', $request->email)->first();

            // 1) メール or パスワードが不一致 → 共通のエラーメッセージ
            if (!$user || !Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    // セキュリティ上の観点から文言はぼかしておく
                    'email' => 'ログイン情報が登録されていません',
                ]);
            }

            // 2) /login からの認証なら admin を明示的に拒否する
            //    ※ ルート/パスの判定は環境差異に強いように複数条件を用意
            if ($request->is('login') || $request->routeIs('login') || trim($request->path(), '/') === 'login') {
                if (($user->role ?? null) === 'admin') {
                    throw ValidationException::withMessages([
                        Fortify::username() => 'ログイン情報が登録されていません',
                    ]);  // セキュリティ上の観点から文言はぼかしておく
                }
            }

            // 3) ここまで通ったらログイン成功（一般ユーザー or /admin/login ではない経路）
            return $user;
        });
    }
}
