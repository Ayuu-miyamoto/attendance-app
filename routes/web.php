<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AdminLoginController;
use App\Http\Controllers\AdminAttendanceController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

// ==================================================================================
// 一般ユーザー
// ==================================================================================

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

// 勤怠修正申請
Route::post('/attendance/{id}', [
    AttendanceController::class,
    'requestCorrection'
])->whereNumber('id');

// 申請詳細画面
Route::get('/application/{id}', function ($id) {
    $application = \App\Models\AttendanceCorrect::findOrFail($id);

    return redirect('/attendance/detail/' . $application->attendance_record_id);
})->middleware('auth');

// ==================================================================================
// 一般ユーザー・管理者共通
// ==================================================================================

// 勤怠詳細画面
Route::get('/attendance/{id}', function (int $id) {
    if (Auth::user()->admin_status) {
        return app(AdminAttendanceController::class)->show($id);
    }

    return app(AttendanceController::class)->show($id);
})->middleware('auth')->whereNumber('id');

// ==================================================================================
// 管理者
// ==================================================================================

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

// 修正申請の詳細画面
Route::get(
    '/stamp_correction_request/approve/{attendance_correct_request_id}',
    [AdminAttendanceController::class, 'showApplication']
)->middleware('admin')->whereNumber('attendance_correct_request_id');

// 修正申請の承認処理
Route::post(
    '/stamp_correction_request/approve/{attendance_correct_request_id}',
    [AdminAttendanceController::class, 'approveApplication']
)->middleware('admin')->whereNumber('attendance_correct_request_id');


// ==================================================================================
// 一般ユーザー・管理者共通
// ==================================================================================
// 修正申請一覧画面
Route::get('/stamp_correction_request/list', function () {
    if (Auth::user()->admin_status) {
        return app(AdminAttendanceController::class)
            ->applicationList(request());
    }

    return app(AttendanceController::class)
        ->correctionRequestList();
})->middleware('auth');