<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceBreak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        // 勤怠がない、または出勤時刻が未登録の場合は勤務外とする
        if (!$attendanceRecord || !$attendanceRecord->clock_in) {

            // 勤務外ステータスを設定する
            $attendanceStatus = '勤務外';

        // 出勤時刻が登録されている場合
        } else {

            // Modelから勤怠ステータスを取得する
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

        // 同じユーザーの打刻と修正申請を順番に処理する
        DB::transaction(function () use ($user, $action): void {

            // 同じユーザーの同時更新を防ぐためユーザー行をロックする
            $user->newQuery()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            // 当日の勤怠レコードを取得する
            $attendanceRecord = AttendanceRecord::where('user_id', $user->id)
                ->whereDate('date', today())
                ->first();

            // 当日に承認待ちの修正申請があるか確認する
            $hasPendingApplication = $attendanceRecord
                && AttendanceCorrect::where(
                    'attendance_record_id',
                    $attendanceRecord->id
                )
                    ->where('approval_status', false)
                    ->exists();

            // 承認待ちの修正申請がある場合は打刻を変更しない
            if ($hasPendingApplication) {
                return;
            }

            // 出勤操作の場合
            if ($action === 'clock_in') {

                // 当日の勤怠レコードが存在しない場合
                if (!$attendanceRecord) {

                    // 新しい勤怠レコードを作成する
                    AttendanceRecord::create([
                        'user_id' => $user->id,
                        'date' => today(),
                        'clock_in' => now()->format('H:i:s'),
                    ]);

                // 空欄の勤怠レコードが存在する場合
                } elseif (!$attendanceRecord->clock_in) {

                    // 既存の勤怠レコードに出勤時刻を保存する
                    $attendanceRecord->update([
                        'clock_in' => now()->format('H:i:s'),
                    ]);
                }
            }

            // 退勤操作の場合
            elseif ($action === 'clock_out') {

                // 出勤済みかつ未退勤の場合のみ退勤を保存する
                if (
                    $attendanceRecord
                    && $attendanceRecord->clock_in
                    && !$attendanceRecord->clock_out
                ) {
                    // 退勤時刻を保存する
                    $attendanceRecord->update([
                        'clock_out' => now()->format('H:i:s'),
                    ]);
                }
            }

            // 休憩開始操作の場合
            elseif ($action === 'break_in') {

                // 出勤済みかつ未退勤の場合
                if (
                    $attendanceRecord
                    && $attendanceRecord->clock_in
                    && !$attendanceRecord->clock_out
                ) {

                    // 終了していない休憩が存在するか確認する
                    $hasOpenBreak = $attendanceRecord->breaks()
                        ->whereNull('break_out')
                        ->exists();

                    // 休憩中でなければ新しい休憩を作成する
                    if (!$hasOpenBreak) {

                        // 休憩開始時刻を保存する
                        AttendanceBreak::create([
                            'attendance_record_id' => $attendanceRecord->id,
                            'break_in' => now()->format('H:i:s'),
                        ]);
                    }
                }
            }

            // 休憩終了操作の場合
            elseif ($action === 'break_out') {

                // 出勤済みかつ未退勤の場合
                if (
                    $attendanceRecord
                    && $attendanceRecord->clock_in
                    && !$attendanceRecord->clock_out
                ) {

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

                        // 休憩終了時刻を保存する
                        $break->update([
                            'break_out' => now()->format('H:i:s'),
                        ]);
                    }
                }
            }
        });

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

        // 表示する月の初日を取得する
        $startDate = $date->copy()->startOfMonth();

        // 表示する月の末日を取得する
        $endDate = $date->copy()->endOfMonth();

        // 前月を取得する
        $previousMonth = $date->copy()
            ->subMonthNoOverflow()
            ->format('Y-m');

        // 翌月を取得する
        $nextMonth = $date->copy()
            ->addMonthNoOverflow()
            ->format('Y-m');

        // ログインユーザーの指定月の勤怠を取得する
        $attendanceRecords = AttendanceRecord::with('breaks')
            ->where('user_id', $user->id)
            ->whereBetween('date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->get()

            // 日付をキーにして勤怠を検索できるようにする
            ->keyBy(function (AttendanceRecord $attendance): string {

                // 勤怠の日付をYYYY-MM-DD形式で返す
                return $attendance->date->format('Y-m-d');
            });

        // 一覧表示用の配列を用意する
        $formattedAttendanceRecords = [];

        // 月初から月末まで1日ずつ処理する
        for (
            $day = $startDate->copy();
            $day->lte($endDate);
            $day->addDay()
        ) {

            // 処理中の日付を文字列で取得する
            $dayString = $day->format('Y-m-d');

            // その日の勤怠レコードを取得する
            $attendance = $attendanceRecords->get($dayString);

            // 勤怠レコードが存在しない場合
            if (!$attendance) {

                // 日付形式のIDと空欄の勤怠情報を設定する
                $formattedAttendanceRecords[] = [
                    'id' => $dayString,
                    'date' => $day->format('m/d'),
                    'clock_in' => '',
                    'clock_out' => '',
                    'total_break_time' => null,
                    'total_time' => null,
                ];

                // 次の日付に進む
                continue;
            }

            // Modelから合計休憩時間を取得する
            $totalBreakMinutes = $attendance->total_break_minutes;

            // 合計休憩時間をCarbonオブジェクトに変換する
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

                // 休憩時間を差し引いて実働時間を計算する
                $totalWorkMinutes = $clockIn->diffInMinutes($clockOut)
                    - $totalBreakMinutes;

                // 実働時間をCarbonオブジェクトに変換する
                $totalTime = Carbon::createFromTime(0, 0)
                    ->addMinutes($totalWorkMinutes);
            }

            // 勤怠が存在する日の表示情報を作成する
            $formattedAttendanceRecords[] = [
                'id' => $attendance->id,

                'date' => $day->format('m/d'),

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
     * @param int|string $id 勤怠IDまたはYYYY-MM-DD形式の日付
     * @return View|RedirectResponse 勤怠詳細画面またはリダイレクト
     */
    public function show(int|string $id): View|RedirectResponse
    {
        // ログイン中のユーザーを取得する
        $user = Auth::user();

        // 日付形式のIDが指定された場合
        if (!ctype_digit((string) $id)) {

            // 指定された日付を厳密に解析する
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $id);

            // 正しい日付でなければ404エラーにする
            abort_unless(
                $date !== false
                    && $date->format('Y-m-d') === (string) $id,
                404
            );

            // 指定された日付の勤怠レコードを取得する
            $attendanceRecord = AttendanceRecord::with('breaks')
                ->where('user_id', $user->id)
                ->whereDate('date', $id)
                ->first();

            // すでに勤怠レコードが存在する場合
            if ($attendanceRecord) {

                // 数値IDの詳細画面へ移動する
                return redirect('/attendance/' . $attendanceRecord->id);
            }

            // 日付をCarbonオブジェクトに変換する
            $displayDate = Carbon::parse($id);

            // 未出勤日の詳細表示用データを作成する
            $data = [
                'id' => $id,
                'year' => $displayDate->format('Y年'),
                'date' => $displayDate->format('n月j日'),
                'clock_in' => '',
                'clock_out' => '',
                'breaks' => [],
                'comment' => '',
                'application' => null,
            ];

            // 未出勤日の勤怠詳細画面を表示する
            return view('user.user-detail', compact('user', 'data'));
        }

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

                    // 休憩時刻を表示用の配列にまとめる
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

                    // 休憩時刻を表示用の配列にまとめる
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
     * @param int|string $id 勤怠IDまたはYYYY-MM-DD形式の日付
     * @return RedirectResponse 勤怠詳細画面へのリダイレクト
     */
    public function requestCorrection(
        AttendanceCorrectionRequest $request,
        int|string $id
    ): RedirectResponse {

        // ログイン中のユーザーを取得する
        $user = Auth::user();

        // バリデーション済みの入力値を取得する
        $validated = $request->validated();

        // 保存後の勤怠IDを格納する変数を用意する
        $attendanceId = null;

        // 勤怠レコードと修正申請をまとめて保存する
        DB::transaction(function () use (
            $user,
            $id,
            $validated,
            &$attendanceId
        ): void {

            // 同じユーザーの同時申請を防ぐためユーザー行をロックする
            $user->newQuery()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            // 数値IDが指定された場合
            if (ctype_digit((string) $id)) {

                // 本人の勤怠レコードだけを取得する
                $attendanceRecord = AttendanceRecord::where(
                    'user_id',
                    $user->id
                )
                    ->findOrFail($id);

            // 日付形式のIDが指定された場合
            } else {

                // 指定された日付を厳密に解析する
                $date = \DateTimeImmutable::createFromFormat(
                    '!Y-m-d',
                    (string) $id
                );

                // 不正な日付の場合は404エラーにする
                abort_unless(
                    $date !== false
                        && $date->format('Y-m-d') === (string) $id,
                    404
                );

                // 同じ日付の既存勤怠を取得する
                $attendanceRecord = AttendanceRecord::where(
                    'user_id',
                    $user->id
                )
                    ->whereDate('date', $id)
                    ->first();

                // 勤怠レコードが存在しない場合
                if (!$attendanceRecord) {

                    // 出退勤時刻が空欄の勤怠レコードを作成する
                    $attendanceRecord = AttendanceRecord::create([
                        'user_id' => $user->id,
                        'date' => $id,
                        'clock_in' => null,
                        'clock_out' => null,
                    ]);
                }
            }

            // 保存後のリダイレクト先に使う勤怠IDを取得する
            $attendanceId = $attendanceRecord->id;

            // 承認待ちの修正申請が存在するか確認する
            $hasPendingApplication = AttendanceCorrect::where(
                'attendance_record_id',
                $attendanceRecord->id
            )
                ->where('approval_status', false)
                ->exists();

            // 承認待ちの申請がある場合
            if ($hasPendingApplication) {

                // 新しい申請を作成せず処理を終了する
                return;
            }

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

            // 開始・終了のどちらかに存在するインデックスを取得する
            $indexes = array_unique(array_merge(
                array_keys($breakIns),
                array_keys($breakOuts)
            ));

            // 休憩データを1件ずつ処理する
            foreach ($indexes as $index) {

                // 休憩開始時刻を取得する
                $breakIn = $breakIns[$index] ?? null;

                // 休憩終了時刻を取得する
                $breakOut = $breakOuts[$index] ?? null;

                // 両方空欄の場合は保存しない
                if (blank($breakIn) && blank($breakOut)) {
                    continue;
                }

                // 休憩修正データを保存する
                $application->breakCorrects()->create([
                    'break_in' => filled($breakIn) ? $breakIn : null,
                    'break_out' => filled($breakOut) ? $breakOut : null,
                ]);
            }
        });

        // 保存後は数値IDの勤怠詳細画面へ移動する
        return redirect('/attendance/' . $attendanceId);
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