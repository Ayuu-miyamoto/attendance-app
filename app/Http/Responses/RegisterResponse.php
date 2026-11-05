<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class RegisterResponse implements RegisterResponseContract
{
    // 会員登録が完了したときのレスポンスを返す
    public function toResponse($request)
    {
        // APIなどのJSONリクエストには標準の成功レスポンスを返す
        if ($request->wantsJson()) {
            return response()->json('', 201);
        }

        // 通常の会員登録後はメール認証画面へ移動する
        return redirect()->route('verification.notice');
    }
}