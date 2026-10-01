<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\AdminAttendanceController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StampCorrectionController;
use App\Http\Controllers\AuthAttendanceController;

// 管理者
// ログイン画面
Route::get('/admin/login', function () {
    return view('admin.admin-login');
});
Route::post('/admin/login', [AuthenticatedSessionController::class, 'store']);

Route::middleware('auth')->group(function () {
    Route::get('/admin/attendance/list', [AdminAttendanceController::class, 'index']);
    Route::get('/admin/attendance/{id}', [AdminAttendanceController::class, 'show']);
    Route::get('/admin/staff/list', [StaffController::class, 'index']);
    Route::get('/admin/attendance/staff/{id}', [StaffController::class, 'show']);
    Route::get('/stamp_correction_request/list', [StampCorrectionController::class, 'index']);
    Route::get('/stamp_correction_request/approve/{attendance_correct_request_id}', [StampCorrectionController::class, 'show']);
});


// 一般ユーザー
// 新規登録画面
Route::get('/register', function () {
    return view('user.register');
});

// ログイン画面
Route::get('/login', function () {
    return view('user.user-login');
});

// 勤怠登録画面
Route::get('/attendance', [AuthAttendanceController::class, 'index'])
    ->middleware('auth');