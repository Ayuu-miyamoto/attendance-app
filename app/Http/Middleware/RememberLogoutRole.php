<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RememberLogoutRole
{
    // ログアウト前にユーザーの種類を記録する
    public function handle(Request $request, Closure $next)
    {
        // ログアウト以外のリクエストはそのまま通す
        if (!$request->isMethod('POST') || !$request->is('logout')) {
            return $next($request);
        }

        // ログイン中のユーザーを取得する
        $user = Auth::guard('web')->user();

        // 管理者かどうかを判定する
        $isAdmin = (bool) ($user?->admin_status ?? false);

        // ログアウト後も参照できるリクエスト属性に記録する
        $request->attributes->set('logout_is_admin', $isAdmin);

        // Fortifyのログアウト処理へ進む
        return $next($request);
    }
}
