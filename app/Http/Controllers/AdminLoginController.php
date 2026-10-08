<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminLoginController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required',
            'password' => 'required',
        ], [
            'email.required' => 'メールアドレスを入力してください',
            'password.required' => 'パスワードを入力してください',
        ]);

        if (Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'admin_status' => true,
        ])) {
            $request->session()->regenerate();

            return redirect('/admin/attendance/list');
        }

        return back()->withErrors([
            'email' => 'ログイン情報が登録されていません',
        ])->withInput();
    }
}