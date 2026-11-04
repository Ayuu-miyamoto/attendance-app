<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceBreak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\AttendanceCorrect;
use App\Models\BreakCorrect;
use App\Http\Requests\AttendanceCorrectionRequest;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class AttendanceController extends Controller
{
    /**
     * 一般ユーザーの勤怠打刻画面を表示する。
     *
     * @return View 勤怠打刻画面
     */
    public function index(): View
    {
        // ログイン中のユーザー情報を取得する
        $user = Auth::user();

        // 当日の勤怠レコードを取得する
        $attendanceRecord = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('date', today())
            ->first();

        // 勤怠ステータスを取得する
        // もし勤怠レコードがなければ勤務外とする
        if (!$attendanceRecord) {
            $attendanceStatus = '勤務外';

        // 勤怠レコードがある場合は、勤怠ステータスを取得する
        } else {
            $attendanceStatus = $attendanceRecord->getAttendanceStatus();
        }

        // ユーザーに勤怠ステータスを設定する
        $user->attendance_status = $attendanceStatus;

        // 日本語の曜日を定義する
        $weekdays = ['日', '月', '火', '水', '木', '金', '土'];

        // 現在の日付を日本語形式に変換する
        $formattedDate = Carbon::now()->format('Y年n月j日')
            . '(' . $weekdays[Carbon::now()->dayOfWeek] . ')';

        // 現在の時刻を取得する
        $formattedTime = Carbon::now()->format('H:i');

        // 勤怠打刻画面を表示する
        return view('user.attendance-register', compact(
            'user',
            'formattedDate',
            'formattedTime'
        ));
    }

    /**
     * 出勤・退勤・休憩開始・休憩終了の打刻を処理する。
     *
     * @param Request $request 打刻操作を含むリクエスト
     * @return RedirectResponse 勤怠打刻画面へのリダイレクト
     */
    public function store(Request $request): RedirectResponse
    {
        // ログイン中のユーザーを取得する
        $user = Auth::user();

        // 打刻操作の種類を取得する
        $action = $request->input('action');

        // 当日の勤怠レコードを取得する
        $attendanceRecord = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('date', today())
            ->first();

        // 出勤
        if ($action === 'clock_in') {

            // 当日の勤怠レコードがなければ出勤記録を作成する
            if (!$attendanceRecord) {
                AttendanceRecord::create([
                    'user_id' => $user->id,
                    'date' => today(),
                    'clock_in' => now()->format('H:i:s'),
                ]);
            }
        }

        // 退勤
        elseif ($action === 'clock_out') {

            // 勤怠レコードが存在する場合
            if ($attendanceRecord) {
                $attendanceRecord->update([
                    'clock_out' => now()->format('H:i:s'),
                ]);
            }
        }

        // 休憩入
        elseif ($action === 'break_in') {

            // 勤怠レコードが存在する場合
            if ($attendanceRecord) {
                AttendanceBreak::create([
                    'attendance_record_id' => $attendanceRecord->id,
                    'break_in' => now()->format('H:i:s'),
                ]);
            }
        }

        // 休憩戻
        elseif ($action === 'break_out') {

            // 勤怠レコードが存在する場合
            if ($attendanceRecord) {

                // 終了していない最新の休憩を取得する
                $break = AttendanceBreak::where(
                    'attendance_record_id',
                    $attendanceRecord->id
                )
                    ->whereNull('break_out')
                    ->latest()
                    ->first();

                // 対象の休憩が存在する場合
                if ($break) {
                    $break->update([
                        'break_out' => now()->format('H:i:s'),
                    ]);
                }
            }
        }

        // 勤怠打刻画面に戻る
        return redirect('/attendance');
    }

    /**
     * ログインユーザーの月次勤怠一覧を表示する。
     *
     * @param Request $request 表示する年月を含むリクエスト
     * @return View 勤怠一覧画面
     */
    public function list(Request $request): View
    {
        // ログイン中のユーザーを取得する
        $user = Auth::user();

        // 指定された年月、または現在の日付を取得する
        $date = $request->date
            ? Carbon::parse($request->date)
            : Carbon::today();

        // 前月を取得する
        $previousMonth = $date->copy()
            ->subMonth()
            ->format('Y-m');

        // 翌月を取得する
        $nextMonth = $date->copy()
            ->addMonth()
            ->format('Y-m');

        // ログインユーザーの指定月の勤怠を休憩情報と一緒に取得する
        $attendanceRecords = AttendanceRecord::with('breaks')
            ->where('user_id', $user->id)
            ->whereYear('date', $date->year)
            ->whereMonth('date', $date->month)
            ->get();

        // 勤怠データを画面表示用の配列に変換する
        $formattedAttendanceRecords = $attendanceRecords->map(
            function (AttendanceRecord $attendance): array {

                // Modelから合計休憩時間（分）を取得する
                $totalBreakMinutes = $attendance->total_break_minutes;

                // 合計休憩時間をCarbonで扱える形式に変換する
                $totalBreakTime = Carbon::createFromTime(0, 0)
                    ->addMinutes($totalBreakMinutes);

                // 実働時間の初期値を設定する
                $totalTime = null;

                // 出勤・退勤時刻が両方登録されている場合
                if ($attendance->clock_in && $attendance->clock_out) {

                    // 出勤時刻を取得する
                    $clockIn = Carbon::parse($attendance->clock_in);

                    // 退勤時刻を取得する
                    $clockOut = Carbon::parse($attendance->clock_out);

                    // 実働時間から休憩時間を差し引く
                    $totalWorkMinutes = $clockIn->diffInMinutes($clockOut)
                        - $totalBreakMinutes;

                    // 実働時間をCarbonで扱える形式に変換する
                    $totalTime = Carbon::createFromTime(0, 0)
                        ->addMinutes($totalWorkMinutes);
                }

                // Bladeに渡す勤怠情報を配列にまとめる
                return [
                    'id' => $attendance->id,

                    'date' => Carbon::parse($attendance->date)
                        ->format('m/d'),

                    'clock_in' => $attendance->clock_in
                        ? Carbon::parse($attendance->clock_in)->format('H:i')
                        : '',

                    'clock_out' => $attendance->clock_out
                        ? Carbon::parse($attendance->clock_out)->format('H:i')
                        : '',

                    'total_break_time' => $totalBreakTime,

                    'total_time' => $totalTime,
                ];
            }
        )->toArray();

        // 勤怠一覧画面を表示する
        return view('user.user-attendance-list', compact(
            'date',
            'previousMonth',
            'nextMonth',
            'formattedAttendanceRecords'
        ));
    }

    /**
     * 指定された勤怠の詳細画面を表示する。
     *
     * @param int $id 勤怠レコードのID
     * @return View 勤怠詳細画面
     */
    public function show(int $id): View
    {
        // ログイン中のユーザーを取得する
        $user = Auth::user();

        // ログイン中のユーザー本人の勤怠だけ取得する
        $attendanceRecord = AttendanceRecord::with('breaks')
            ->where('user_id', $user->id)
            ->findOrFail($id);

        // 承認待ちの修正申請を取得する
        $application = AttendanceCorrect::with('breakCorrects')
            ->where('attendance_record_id', $attendanceRecord->id)
            ->where('approval_status', false)
            ->latest()
            ->first();

        // 承認待ち申請があれば申請内容を表示する
        $displayClockIn = $application
            ? $application->clock_in
            : $attendanceRecord->clock_in;

        // 承認待ち申請があれば申請内容を表示する
        $displayClockOut = $application
            ? $application->clock_out
            : $attendanceRecord->clock_out;

        // 承認待ち申請があれば備考を表示する
        $displayMemo = $application
            ? $application->comment
            : '';

        // 承認待ちの修正申請がある場合
        if ($application) {

            // 申請された休憩データを表示用に変換する
            $breaks = $application->breakCorrects->map(
                function (BreakCorrect $break): array {
                    return [
                        'break_in' => $break->break_in
                            ? Carbon::parse($break->break_in)->format('H:i')
                            : '',

                        'break_out' => $break->break_out
                            ? Carbon::parse($break->break_out)->format('H:i')
                            : '',
                    ];
                }
            )->toArray();

        // 承認待ちの修正申請がない場合
        } else {

            // 元の休憩データを表示用に変換する
            $breaks = $attendanceRecord->breaks->map(
                function (AttendanceBreak $break): array {
                    return [
                        'break_in' => $break->break_in
                            ? Carbon::parse($break->break_in)->format('H:i')
                            : '',

                        'break_out' => $break->break_out
                            ? Carbon::parse($break->break_out)->format('H:i')
                            : '',
                    ];
                }
            )->toArray();
        }

        // 勤怠の日付を取得する
        $date = Carbon::parse($attendanceRecord->date);

        // Bladeに渡す勤怠詳細データを作成する
        $data = [
            'id' => $attendanceRecord->id,
            'year' => $date->format('Y年'),
            'date' => $date->format('n月j日'),

            'clock_in' => $displayClockIn
                ? Carbon::parse($displayClockIn)->format('H:i')
                : '',

            'clock_out' => $displayClockOut
                ? Carbon::parse($displayClockOut)->format('H:i')
                : '',

            'breaks' => $breaks,
            'comment' => $displayMemo ?? '',
            'application' => $application,
        ];

        // 勤怠詳細画面を表示する
        return view('user.user-detail', compact('user', 'data'));
    }

    /**
     * 勤怠修正申請と休憩修正内容を保存する。
     *
     * @param AttendanceCorrectionRequest $request バリデーション済みの申請リクエスト
     * @param int $id 修正対象の勤怠レコードID
     * @return RedirectResponse 勤怠詳細画面へのリダイレクト
     */
    public function requestCorrection(
        AttendanceCorrectionRequest $request,
        int $id
    ): RedirectResponse {
        // ログイン中のユーザーを取得する
        $user = Auth::user();

        // ログイン中のユーザー本人の勤怠だけ取得する
        $attendanceRecord = AttendanceRecord::where('user_id', $user->id)
            ->findOrFail($id);

        // 承認待ちの申請がある場合は、新しい申請を作成しない
        $hasPendingApplication = AttendanceCorrect::where(
            'attendance_record_id',
            $attendanceRecord->id
        )
            ->where('approval_status', false)
            ->exists();

        // 承認待ちの申請が存在する場合
        if ($hasPendingApplication) {

            // 勤怠詳細画面に戻る
            return redirect('/attendance/' . $id);
        }

        // バリデーション済みの入力値を取得する
        $validated = $request->validated();

        // 勤怠修正申請を保存する
        $application = AttendanceCorrect::create([
            'attendance_record_id' => $attendanceRecord->id,
            'clock_in' => $validated['new_clock_in'],
            'clock_out' => $validated['new_clock_out'],
            'comment' => $validated['comment'],
            'approval_status' => false,
            'requested_at' => now(),
        ]);

        // 休憩開始時刻の配列を取得する
        $breakIns = $validated['new_break_in'] ?? [];

        // 休憩終了時刻の配列を取得する
        $breakOuts = $validated['new_break_out'] ?? [];

        // 休憩開始時刻の配列をCollectionに変換する
        collect($breakIns)

            // 開始・終了が両方空の休憩データを除外する
            ->filter(function ($breakIn, $index) use ($breakOuts): bool {

                // 同じ位置にある休憩終了時刻を取得する
                $breakOut = $breakOuts[$index] ?? null;

                // 開始・終了のどちらかに値があれば残す
                return !blank($breakIn) || !blank($breakOut);
            })

            // 残った休憩データを1件ずつ保存する
            ->each(function ($breakIn, $index) use ($breakOuts, $application): void {

                // 同じ位置にある休憩終了時刻を取得する
                $breakOut = $breakOuts[$index] ?? null;

                // 休憩修正データを保存する
                $application->breakCorrects()->create([
                    'break_in' => $breakIn ?: null,
                    'break_out' => $breakOut ?: null,
                ]);
            });

        // 修正申請の保存後、勤怠詳細画面に戻る
        return redirect('/attendance/' . $id);
    }

    /**
     * ログイン中のユーザーの勤怠修正申請一覧を表示する。
     *
     * @return View 勤怠修正申請一覧画面
     */
    public function correctionRequestList(): View
    {
        // ログイン中のユーザーを取得する
        $user = Auth::user();

        // 修正申請一覧用のデータを取得する
        $formattedApplications =
            AttendanceCorrect::getFormattedApplications($user->id);

        // 勤怠修正申請一覧画面を表示する
        return view('user.user-application-list', compact(
            'user',
            'formattedApplications'
        ));
    }
}
