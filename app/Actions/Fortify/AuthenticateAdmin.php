<?php

namespace App\Actions\Fortify;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateAdmin
{
    public function handle(Request $request, Closure $next)
    {
        // 管理者ログインの場合
        if ($request->is('admin/login')) {

            // 管理者ログインの入力チェック
            $credentials = $request->validate([
                'email' => 'required',
                'password' => 'required',
            ], [
                'email.required' => 'メールアドレスを入力してください',
                'password.required' => 'パスワードを入力してください',
            ]);

            // 管理者権限を持つユーザーだけ認証する
            if (Auth::guard('admin')->attempt([
                'email' => $credentials['email'],
                'password' => $credentials['password'],
                'admin_status' => true,
            ])) {

                // 一般ユーザーのログイン状態を解除する
                Auth::guard('web')->logout();

                // セッションIDを再生成する
                $request->session()->regenerate();

                // 管理者の勤怠一覧画面へ移動する
                return redirect('/admin/attendance/list');
            }

            // 認証失敗時のエラーを返す
            return back()->withErrors([
                'email' => 'ログイン情報が登録されていません',
            ])->withInput();
        }

        // 一般ログインの場合
        if ($request->is('login')) {

            // 一般ログインの入力チェック
            $credentials = $request->validate([
                'email' => 'required',
                'password' => 'required',
            ], [
                'email.required' => 'メールアドレスを入力してください',
                'password.required' => 'パスワードを入力してください',
            ]);

            // 一般ユーザーだけ認証する
            if (! Auth::guard('web')->attempt([
                'email' => $credentials['email'],
                'password' => $credentials['password'],
                'admin_status' => false,
            ])) {

                // 認証失敗時のエラーを返す
                return back()->withErrors([
                    'email' => 'ログイン情報が登録されていません',
                ])->withInput();
            }

            // 管理者のログイン状態を解除する
            Auth::guard('admin')->logout();

            // Fortifyの後続処理へ進む
            return $next($request);
        }

        // その他の処理はFortifyに任せる
        return $next($request);
    }
}
