<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;
use App\Actions\Fortify\AuthenticateAdmin;
use Laravel\Fortify\Actions\AttemptToAuthenticate;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ログアウト後のリダイレクト処理を独自クラスに変更する
        $this->app->singleton(
            \Laravel\Fortify\Contracts\LogoutResponse::class,
            \App\Http\Responses\LogoutResponse::class
        );

        // 会員登録後のレスポンスを独自クラスに変更する
        $this->app->singleton(
            \Laravel\Fortify\Contracts\RegisterResponse::class,
            \App\Http\Responses\RegisterResponse::class
        );

        // メール認証が必要なユーザーに表示する画面を指定する
        Fortify::verifyEmailView(function () {
            // 既存のメール認証画面を表示する
            return view('auth.verify-email');
        });

        // 会員登録処理を設定する
        Fortify::createUsersUsing(CreateNewUser::class);

        // プロフィール更新処理を設定する
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);

        // パスワード更新処理を設定する
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);

        // パスワードリセット処理を設定する
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // 二段階認証のリダイレクト処理を設定する
        Fortify::redirectUserForTwoFactorAuthenticationUsing(
            RedirectIfTwoFactorAuthenticatable::class
        );

        // ログインの試行回数を制限する
        RateLimiter::for('login', function (Request $request) {

            // メールアドレスとIPアドレスから識別キーを作成する
            $throttleKey = Str::transliterate(
                Str::lower($request->input(Fortify::username())).'|'.$request->ip()
            );

            // 1分間に5回まで許可する
            return Limit::perMinute(5)->by($throttleKey);
        });

        // 二段階認証の試行回数を制限する
        RateLimiter::for('two-factor', function (Request $request) {

            // セッション内のログインIDを使って制限する
            return Limit::perMinute(5)->by(
                $request->session()->get('login.id')
            );
        });

        // Fortifyのログイン認証処理を設定する
        Fortify::authenticateThrough(function (Request $request) {

            // 認証処理を順番に実行する
            return array_filter([
                \Laravel\Fortify\Actions\EnsureLoginIsNotThrottled::class,
                RedirectIfTwoFactorAuthenticatable::class,
                AuthenticateAdmin::class,
                AttemptToAuthenticate::class,
                \Laravel\Fortify\Actions\PrepareAuthenticatedSession::class,
            ]);
        });
    }
}
