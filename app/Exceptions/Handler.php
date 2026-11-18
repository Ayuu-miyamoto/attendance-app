<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * バリデーションエラー時にセッションへ保存しない入力項目。
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * 例外発生時の処理を登録する。
     */
    public function register(): void
    {
        // Laravel標準の例外報告処理を登録する
        $this->reportable(function (Throwable $e) {
            //
        });

        // 勤怠データが存在しない場合の404エラーを処理する
        $this->renderable(function (ModelNotFoundException $exception, Request $request) {

            // APIへのリクエストの場合だけ独自のJSONを返す
            if ($request->is('api/*')) {

                // 日本語のエラーメッセージと404ステータスを返す
                return response()->json([
                    'error' => '勤怠情報が見つかりませんでした。',
                ], 404);
            }

            // Web側はLaravel標準のエラー処理に任せる
            return null;
        });

        // 存在しない勤怠詳細APIへの404エラーを処理する
        $this->renderable(function (NotFoundHttpException $exception, Request $request) {

            // 勤怠詳細APIへのリクエストの場合だけ独自のJSONを返す
            if ($request->is('api/v1/attendance-records/*')) {

                // 日本語のエラーメッセージと404ステータスを返す
                return response()->json([
                    'error' => '勤怠情報が見つかりませんでした。',
                ], 404);
            }

            // Web側はLaravel標準のエラー処理に任せる
            return null;
        });

        // APIで認可エラーが発生した場合の403エラーを処理する
        $this->renderable(function (AccessDeniedHttpException $exception, Request $request) {

            // APIへのリクエストの場合だけ独自のJSONを返す
            if ($request->is('api/*')) {

                // 日本語のエラーメッセージと403ステータスを返す
                return response()->json([
                    'error' => 'この操作を実行する権限がありません。',
                ], 403);
            }

            // Web側はLaravel標準のエラー処理に任せる
            return null;
        });
    }
}
