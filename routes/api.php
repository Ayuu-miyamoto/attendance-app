<?php

use App\Http\Controllers\Api\V1\AttendanceRecordController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Sanctum認証が必要なユーザー情報取得API
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// APIバージョンv1のルートをまとめる
Route::prefix('v1')->group(function (): void {

    // AP01：勤怠一覧を取得する
    Route::get('/attendance-records', [
        AttendanceRecordController::class,
        'index',
    ]);

    // AP02：指定された勤怠IDの詳細情報を取得する
    Route::get('/attendance-records/{attendanceRecord}', [
        AttendanceRecordController::class,
        'show',
    ])->whereNumber('attendanceRecord');
});
