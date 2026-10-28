<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminLoginController extends Controller
{
    /**
     * 管理者ログイン処理
     */
    // Request $request には、Bladeから送信された情報が入っている
    public function login(Request $request)
    {
        // バリデーション入力値の検証
        $credentials = $request->validate([
            'email' => 'required',
            'password' => 'required',
        ], [
            'email.required' => 'メールアドレスを入力してください',
            'password.required' => 'パスワードを入力してください',
        ]);

        // Auth::guard('admin'):admin という名前の認証ガードを使用する指定
        // attempt():ログインを試みるメソッド
        if (Auth::guard('admin')->attempt([
            // 入力されたメールアドレスに一致するユーザーを探す条件
            'email' => $credentials['email'],
            // 入力されたパスワードがDBに保存されたハッシュと一致するか確認
            'password' => $credentials['password'],
            // 管理者権限があるか確認
            'admin_status' => true,
        ])) {
            // ログイン成功後、セッションIDを新しくして安全性を高める
            $request->session()->regenerate();
            // ログイン後に管理者用勤怠一覧画面にリダイレクト
            return redirect('/admin/attendance/list');
        }
        // ログイン失敗時、ログイン画面に戻る
        return back()->withErrors([
            'email' => 'ログイン情報が登録されていません',
        ])->withInput();
    }
}