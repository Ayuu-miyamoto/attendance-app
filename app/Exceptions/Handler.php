<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        // Laravel標準の例外報告処理
        $this->reportable(function (Throwable $e) {
            //
        });

        // 存在しないURLやデータに対する404エラーを処理する
        $this->renderable(function (NotFoundHttpException $exception, Request $request) {

            // 勤怠詳細APIへのリクエストの場合だけ独自のJSONを返す
            if ($request->is('api/v1/attendance-records/*')) {

                // 日本語のエラーメッセージと404ステータスを返す
                return response()->json([
                    'error' => '勤怠情報が見つかりませんでした。',
                ], 404);
            }

            // それ以外はLaravel標準のエラー処理に任せる
            return null;
        });
    }
}
