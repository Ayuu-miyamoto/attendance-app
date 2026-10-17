<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AdminLoginController;
use App\Http\Controllers\AdminAttendanceController;
use Illuminate\Support\Facades\Route;

// ==============================
// 一般ユーザー
// ==============================

// 登録画面
Route::get('/register', function () {
    return view('user.register');
});

// ログイン画面
Route::get('/login', function () {
    return view('user.user-login');
});

 // 勤怠打刻画面
Route::get('/attendance', [AttendanceController::class, 'index']);
Route::post('/attendance', [AttendanceController::class, 'store']);

// 勤怠一覧画面
Route::get('/attendance/list', [AttendanceController::class, 'list']);

// 勤怠詳細画面（仕様書のURL）
Route::get('/attendance/detail/{id}', [
    AttendanceController::class,
    'show'
])->whereNumber('id');

// Bladeが使用するURLから仕様書のURLへ転送
Route::get('/attendance/{id}', function (int $id) {
    return redirect('/attendance/detail/' . $id);
})->whereNumber('id');

// 勤怠修正申請
Route::post('/attendance/{id}', [
    AttendanceController::class,
    'requestCorrection'
])->whereNumber('id');

// 申請一覧画面
Route::get('/stamp_correction_request/list', [
    AttendanceController::class,
    'correctionRequestList',
]);

// 申請詳細画面
Route::get('/application/{id}', function ($id) {
    $application = \App\Models\AttendanceCorrect::findOrFail($id);

    return redirect('/attendance/detail/' . $application->attendance_record_id);
})->middleware('auth');



// ==============================
// 管理者
// ==============================

// 管理者ログイン画面
Route::get('/admin/login', function () {
    return view('admin.admin-login');
});

// 管理者ログイン処理
Route::post('/admin/login', [
    AdminLoginController::class,
    'login'
]);

// 勤怠一覧画面
Route::get('/admin/attendance/list', [
    AdminAttendanceController::class,
    'index'
])->middleware('admin');

// 勤怠詳細画面
Route::get('/admin/attendance/{id}', [
    AdminAttendanceController::class,
    'show'
])->middleware('admin')->whereNumber('id');
