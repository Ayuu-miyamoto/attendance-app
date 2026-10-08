<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AdminLoginController;
use App\Http\Controllers\AdminAttendanceController;
use Illuminate\Support\Facades\Route;

// 一般ユーザー
Route::get('/', function () {
    return view('welcome');
});
Route::get('/login', function () {
    return view('user.user-login');
});
Route::get('/attendance', [AttendanceController::class, 'index']);
Route::post('/attendance', [AttendanceController::class, 'store']);

Route::get('/attendance/list', [AttendanceController::class, 'list']);


// 管理者
Route::get('/admin/login', function () {
    return view('admin.admin-login');
});
Route::post('/admin/login', [AdminLoginController::class, 'login']);
Route::get('/admin/attendance/list', [AdminAttendanceController::class, 'index'])
    ->middleware('admin');
Route::get('/attendance/{id}', [AdminAttendanceController::class, 'show'])
    ->middleware('admin');