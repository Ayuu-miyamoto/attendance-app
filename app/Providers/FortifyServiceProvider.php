<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Requests\AdminLoginRequest;
use App\Http\Requests\LoginRequest;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // FortifyのLoginRequestを自作のFormRequestに変更
        $this->app->bind(
            \Laravel\Fortify\Http\Requests\LoginRequest::class,
            function ($app) {
                if ($app->make('request')->is('admin/login')) {
                    return $app->make(AdminLoginRequest::class);
                }

                return $app->make(LoginRequest::class);
            }
        );

        // ログイン後の移動先を変更
        $this->app->instance(LoginResponse::class, new class implements LoginResponse {
            public function toResponse($request)
            {
                if (auth()->user()->admin_status == 1) {
                    return redirect('/admin/attendance/list');
                }

                return redirect('/attendance');
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // アクションクラスの登録
        Fortify::createUsersUsing(CreateNewUser::class);

        Fortify::updateUserProfileInformationUsing(
            UpdateUserProfileInformation::class
        );

        Fortify::updateUserPasswordsUsing(
            UpdateUserPassword::class
        );

        Fortify::resetUserPasswordsUsing(
            ResetUserPassword::class
        );

        Fortify::redirectUserForTwoFactorAuthenticationUsing(
            RedirectIfTwoFactorAuthenticatable::class
        );

        // ログイン処理
        Fortify::authenticateUsing(function ($request) {

            $user = \App\Models\User::where(
                'email',
                $request->email
            )->first();

            if (
                $user &&
                Hash::check($request->password, $user->password)
            ) {

                // 管理者ログインの場合は管理者だけ許可
                if (
                    $request->is('admin/login') &&
                    $user->admin_status != 1
                ) {
                    return null;
                }

                return $user;
            }

            return null;
        });

        // ログイン試行回数の制限
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(
                Str::lower(
                    $request->input(Fortify::username())
                ) . '|' . $request->ip()
            );

            return Limit::perMinute(5)->by($throttleKey);
        });

        // 2段階認証のログイン試行回数制限
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->session()->get('login.id'));
        });

        // 管理者ログイン画面
        Fortify::loginView(function () {
            return view('admin.admin-login');
        });
    }
}