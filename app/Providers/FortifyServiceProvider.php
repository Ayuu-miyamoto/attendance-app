<?php

namespace App\Providers;

use App\Actions\Fortify\AuthenticateAdmin;
use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\EnsureLoginIsNotThrottled;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Fortify;

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
            LogoutResponse::class,
            \App\Http\Responses\LogoutResponse::class
        );

        // 会員登録後のレスポンスを独自クラスに変更する
        $this->app->singleton(
            RegisterResponse::class,
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

        // ========================================
        // 一般ユーザー・管理者のログイン認証
        // ========================================

        // Fortifyのログイン認証条件を設定する
        Fortify::authenticateUsing(function (Request $request) {

            // 管理者ログインの場合
            if ($request->is('admin/login')) {

                // 管理者権限を持つユーザーを取得する
                $user = User::where('email', $request->input('email'))
                    ->where('admin_status', true)
                    ->first();

                // 一般ユーザーログインの場合
            } else {

                // 一般ユーザーだけ取得する
                $user = User::where('email', $request->input('email'))
                    ->where('admin_status', false)
                    ->first();
            }

            // ユーザーが存在しない場合は認証失敗とする
            if (! $user) {
                return null;
            }

            // パスワードが一致しない場合は認証失敗とする
            if (! Hash::check($request->input('password', ''), $user->password)) {
                return null;
            }

            // 認証に成功したユーザーを返す
            return $user;
        });

        // ========================================
        // ログイン試行回数の制限
        // ========================================

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

        // ========================================
        // Fortifyのログイン認証処理
        // ========================================

        // Fortifyのログイン認証処理を設定する
        Fortify::authenticateThrough(function (Request $request) {

            // 認証処理を順番に実行する
            return array_filter([
                EnsureLoginIsNotThrottled::class,
                RedirectIfTwoFactorAuthenticatable::class,
                AuthenticateAdmin::class,
                AttemptToAuthenticate::class,
                PrepareAuthenticatedSession::class,
            ]);
        });
    }
}
