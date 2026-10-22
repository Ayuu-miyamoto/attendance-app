<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AdminAttendanceController;
use App\Models\AttendanceCorrect;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\StaffController;

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
Route::get('/attendance', [
    AttendanceController::class,
    'index',
])->middleware('auth');

Route::post('/attendance', [
    AttendanceController::class,
    'store',
])->middleware('auth');

// 勤怠一覧画面
Route::get('/attendance/list', [
    AttendanceController::class,
    'list',
])->middleware('auth');

// 勤怠修正申請
Route::post('/attendance/{id}', [
    AttendanceController::class,
    'requestCorrection',
])->middleware('auth')->whereNumber('id');

// ==================================================================================
// 申請詳細への振り分け
// ==================================================================================

Route::get('/application/{id}', function ($id) {
    $application = AttendanceCorrect::findOrFail($id);

    // 管理者の場合は申請承認画面へ
    if (Auth::user()->admin_status) {
        return redirect(
            '/stamp_correction_request/approve/' . $application->id
        );
    }

    // 一般ユーザーの場合は該当する勤怠詳細画面へ
    return redirect(
        '/attendance/' . $application->attendance_record_id
    );
})->middleware('auth')->whereNumber('id');

// ==================================================================================
// 一般ユーザー・管理者共通
// ==================================================================================

// 勤怠詳細画面
Route::get('/attendance/{id}', [
    // 勤怠詳細画面はAttendanceControllerで表示する
    AttendanceController::class,
    'show',
])->middleware('auth')->whereNumber('id');

// 修正申請一覧画面

 // 修正申請一覧画面
Route::get('/stamp_correction_request/list', function () {
    // 管理者としてログインしている場合
    if (Auth::guard('admin')->check()) {
        Auth::shouldUse('admin');

        return app(AdminAttendanceController::class)
            ->applicationList(request());
    }

    // 一般ユーザーとしてログインしている場合
    if (Auth::guard('web')->check()) {
        Auth::shouldUse('web');

        return app(AttendanceController::class)
            ->correctionRequestList();
    }

    // どちらにもログインしていない場合
    return redirect('/login');
});


// ==================================================================================
// 管理者
// ==================================================================================

// 管理者ログイン画面
Route::get('/admin/login', function () {
    return view('admin.admin-login');
});

// 管理者ログイン処理（Fortify）
Route::post('/admin/login', [
    AuthenticatedSessionController::class,
    'store',
]);

// 管理者の勤怠一覧画面
Route::get('/admin/attendance/list', [
    AdminAttendanceController::class,
    'index',
])->middleware('admin');

// 修正申請の承認画面
Route::get(
    '/stamp_correction_request/approve/{attendance_correct_request_id}',
    [AdminAttendanceController::class, 'showApplication']
)->middleware('admin')->whereNumber('attendance_correct_request_id');

// 修正申請の承認処理
Route::post(
    '/stamp_correction_request/approve/{attendance_correct_request_id}',
    [AdminAttendanceController::class, 'approveApplication']
)->middleware('admin')->whereNumber('attendance_correct_request_id');

// スタッフ一覧
Route::get('/admin/staff/list', [
    StaffController::class,
    'index',
])->middleware('admin');

// スタッフ別勤怠一覧
Route::get('/admin/attendance/staff/{id}', [
    StaffController::class,
    'show',])->middleware('admin')->whereNumber('id');  