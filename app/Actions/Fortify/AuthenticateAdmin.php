<?php

namespace App\Actions\Fortify;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateAdmin
{
    public function handle(Request $request, Closure $next)
    {
        // 管理者ログイン以外はFortifyの通常処理へ進む
        if (!$request->is('admin/login')) {
            $response = $next($request);

            // 一般ユーザーのログイン成功時、管理者のログイン状態を解除
            if ($request->is('login') && Auth::guard('web')->check()) {
                Auth::guard('admin')->logout();
            }

            return $response;
        }

        // 管理者ログインの入力チェック
        $credentials = $request->validate([
            'email' => 'required',
            'password' => 'required',
        ], [
            'email.required' => 'メールアドレスを入力してください',
            'password.required' => 'パスワードを入力してください',
        ]);

        // 管理者アカウントだけ認証する
        if (Auth::guard('admin')->attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'admin_status' => true,
        ])) {
            // 管理者ログイン成功時、一般ユーザーのログイン状態を解除
            Auth::guard('web')->logout();

            $request->session()->regenerate();

            return redirect('/admin/attendance/list');
        }

        return back()->withErrors([
            'email' => 'ログイン情報が登録されていません',
        ])->withInput();
    }
}