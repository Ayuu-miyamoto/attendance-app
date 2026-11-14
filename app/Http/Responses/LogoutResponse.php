<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

class LogoutResponse implements LogoutResponseContract
{
    // ログアウト後の遷移先を決定する
    public function toResponse($request)
    {
        // ログアウト前に記録した管理者フラグを取得する
        $isAdmin = $request->attributes->get('logout_is_admin', false);

        // 管理者は管理者ログイン画面へ移動する
        return redirect($isAdmin ? '/admin/login' : '/login');
    }
}
