<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AdminAttendanceController;
use App\Http\Controllers\StaffController;
use App\Models\AttendanceCorrect;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

// ==================================================================================
// 一般ユーザー
// ==================================================================================

// 会員登録画面
Route::get('/register', function () {
    return view('user.register');
});

// 一般ユーザーのログイン画面を表示する
Route::get('/login', function () {

    // ログイン画面のBladeを返す
    return view('user.user-login');

})->name('login');

// 勤怠打刻画面
Route::get('/attendance', [
    AttendanceController::class,
    'index',
])->middleware(['auth:web', 'verified']);

// 出勤・退勤・休憩の打刻処理
Route::post('/attendance', [
    AttendanceController::class,
    'store',
])->middleware(['auth:web', 'verified']);

// 一般ユーザーの勤怠一覧画面
Route::get('/attendance/list', [
    AttendanceController::class,
    'list',
])->middleware(['auth:web', 'verified']);


// ==================================================================================
// 一般ユーザー・管理者共通
// ==================================================================================

// 一般ユーザー・管理者共通の勤怠詳細画面
Route::get('/attendance/{id}', function (int $id) {

    // 管理者としてログインしている場合
    if (Auth::guard('admin')->check()) {

        // 管理者ガードを使用する
        Auth::shouldUse('admin');

        // 管理者用の勤怠詳細画面を表示する
        return app(AdminAttendanceController::class)->show($id);
    }

    // 一般ユーザーとしてログインしている場合
    if (Auth::guard('web')->check()) {

        // ログイン中のユーザーをUserモデルとして取得する
        /** @var User $user */
        $user = Auth::guard('web')->user();

        // メール認証が完了していない場合は認証案内画面へ移動する
        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        // 一般ユーザーガードを使用する
        Auth::shouldUse('web');

        // 一般ユーザー用の勤怠詳細画面を表示する
        return app(AttendanceController::class)->show($id);
    }

    // 未ログインの場合はログイン画面へ移動する
    return redirect('/login');

})->whereNumber('id');

// 勤怠修正処理
Route::post('/attendance/{id}', function (int $id) {

    // 管理者としてログインしている場合
    if (Auth::guard('admin')->check()) {

        // 管理者ガードを使用する
        Auth::shouldUse('admin');

        // 管理者は勤怠データを直接更新する
        return app()->call(
            [app(AdminAttendanceController::class), 'update'],
            ['id' => $id]
        );
    }

    // 一般ユーザーとしてログインしている場合
    if (Auth::guard('web')->check()) {

        // ログイン中のユーザーをUserモデルとして取得する
        /** @var User $user */
        $user = Auth::guard('web')->user();

        // メール認証が完了していない場合は認証案内画面へ移動する
        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        // 一般ユーザーガードを使用する
        Auth::shouldUse('web');

        // 一般ユーザーは修正申請を作成する
        return app()->call(
            [app(AttendanceController::class), 'requestCorrection'],
            ['id' => $id]
        );
    }

    // 未ログインの場合はログイン画面へ
    return redirect('/login');

})->whereNumber('id');


// ==================================================================================
// 修正申請一覧・詳細
// ==================================================================================

// 一般ユーザー・管理者共通の修正申請一覧画面
Route::get('/stamp_correction_request/list', function () {

    // 管理者としてログインしている場合
    if (Auth::guard('admin')->check()) {

        // 管理者ガードを使用する
        Auth::shouldUse('admin');

        // 管理者用の修正申請一覧を表示する
        return app(AdminAttendanceController::class)
            ->applicationList();
    }

    // 一般ユーザーとしてログインしている場合
    if (Auth::guard('web')->check()) {

        // ログイン中のユーザーをUserモデルとして取得する
        /** @var User $user */
        $user = Auth::guard('web')->user();

        // メール認証が完了していない場合は認証案内画面へ移動する
        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        // 一般ユーザーガードを使用する
        Auth::shouldUse('web');

        // 一般ユーザー用の修正申請一覧を表示する
        return app(AttendanceController::class)
            ->correctionRequestList();
    }

    // 未ログインの場合はログイン画面へ
    return redirect('/login');
});

// 修正申請の詳細画面への振り分け
Route::get('/application/{id}', function (int $id) {

    // 管理者としてログインしている場合
    if (Auth::guard('admin')->check()) {

        // 管理者の申請承認画面へ移動する
        return redirect(
            '/stamp_correction_request/approve/' . $id
        );
    }

    // 一般ユーザーとしてログインしている場合
    if (Auth::guard('web')->check()) {

        // ログイン中のユーザーをUserモデルとして取得する
        /** @var User $user */
        $user = Auth::guard('web')->user();

        // メール認証が完了していない場合は認証案内画面へ移動する
        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        // 修正申請データを取得する
        $application = AttendanceCorrect::findOrFail($id);

        // 自分の勤怠に対する申請か確認する
        $attendance = $application->attendanceRecord;

        // 他人の申請の場合はアクセスを拒否する
        abort_unless(
            $attendance && $attendance->user_id === Auth::guard('web')->id(),
            403
        );

        // 該当する勤怠詳細画面へ移動する
        return redirect(
            '/attendance/' . $application->attendance_record_id
        );
    }

    // 未ログインの場合はログイン画面へ
    return redirect('/login');

})->whereNumber('id');


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

// 管理者の勤怠詳細画面
Route::get('/admin/attendance/{id}', [
    AdminAttendanceController::class,
    'show',
])->middleware('admin')->whereNumber('id');

// 修正申請の承認画面
Route::get(
    '/stamp_correction_request/approve/{attendance_correct_request_id}',
    [
        AdminAttendanceController::class,
        'showApplication',
    ]
)->middleware('admin')->whereNumber('attendance_correct_request_id');

// 修正申請の承認処理
Route::post(
    '/stamp_correction_request/approve/{attendance_correct_request_id}',
    [
        AdminAttendanceController::class,
        'approveApplication',
    ]
)->middleware('admin')->whereNumber('attendance_correct_request_id');

// スタッフ一覧画面
Route::get('/admin/staff/list', [
    StaffController::class,
    'index',
])->middleware('admin');

// スタッフ別の勤怠一覧画面
Route::get('/admin/attendance/staff/{id}', [
    StaffController::class,
    'show',
])->middleware('admin')->whereNumber('id');

// 管理者用CSV出力
Route::post('/export', [
    StaffController::class,
    'export',
])->middleware('admin');
