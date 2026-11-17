<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\AttendanceCorrect;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Models\AttendanceBreak;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class AdminAttendanceController extends Controller
{
    /**
     * 管理者用の日次勤怠一覧画面を表示する。
     */
    public function index(Request $request): View
    {
        // 表示対象の日付を取得する
        $date = $request->date
            ? Carbon::parse($request->date)
            : Carbon::today();

        // 前日の日付を取得する
        $previousDay = $date->copy()
            ->subDay()
            ->format('Y-m-d');

        // 翌日の日付を取得する
        $nextDay = $date->copy()
            ->addDay()
            ->format('Y-m-d');

        // 一般ユーザーの一覧を取得する
        $users = User::where('admin_status', false)->get();

        // 対象日の勤怠を休憩情報と一緒に取得する
        $attendanceRecords = AttendanceRecord::with('breaks')
            ->whereDate('date', $date)
            ->get();

        // 勤怠データを1件ずつ処理する
        $attendanceRecords->each(function (AttendanceRecord $attendance): void {

            // Modelのアクセサから休憩時間を取得する
            $breakMinutes = $attendance->total_break_minutes;

            // 休憩時間をBlade用のCarbonオブジェクトに変換する
            $attendance->total_break_time = Carbon::createFromTime(0, 0)
                ->addMinutes($breakMinutes);

            // 出勤・退勤時刻が登録されている場合
            if ($attendance->clock_in && $attendance->clock_out) {

                // Modelのアクセサから実働時間を取得する
                $workMinutes = $attendance->total_work_minutes;

                // 実働時間をBlade用のCarbonオブジェクトに変換する
                $attendance->total_time = Carbon::createFromTime(0, 0)
                    ->addMinutes($workMinutes);
            }
        });

        // 管理者用勤怠一覧画面を表示する
        return view('admin.admin-attendance-list', compact(
            'date',
            'previousDay',
            'nextDay',
            'users',
            'attendanceRecords'
        ));
    }

    /**
     * 指定された勤怠の詳細画面を表示する。
     *
     * @param int|string $id 勤怠IDまたはスタッフIDと日付
     * @return View|RedirectResponse 詳細画面またはリダイレクト
     */
    public function show(int|string $id): View|RedirectResponse
    {
        // IDが数値ではない場合は未出勤日の詳細として扱う
        if (!ctype_digit((string) $id)) {

            // スタッフIDと日付を分離する
            [$userId, $dateString] = explode('-', (string) $id, 2);

            // 指定されたスタッフを取得する
            $user = User::findOrFail((int) $userId);

            // 日付を厳密に解析する
            $parsedDate = \DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $dateString
            );

            // 正しい日付でなければ404エラーにする
            abort_unless(
                $parsedDate !== false
                    && $parsedDate->format('Y-m-d') === $dateString,
                404
            );

            // 同じスタッフ・日付の勤怠がすでにあるか確認する
            $existingAttendance = AttendanceRecord::where(
                'user_id',
                $user->id
            )
                ->whereDate('date', $dateString)
                ->first();

            // すでに勤怠が存在する場合
            if ($existingAttendance) {

                // 既存の勤怠詳細画面へ移動する
                return redirect('/attendance/' . $existingAttendance->id);
            }

            // 日付を表示用に変換する
            $date = Carbon::parse($dateString);

            // 未出勤日の詳細データを作成する
            $attendanceRecord = [
                'id' => $id,
                'year' => $date->format('Y年'),
                'date' => $date->format('m月d日'),
                'clock_in' => '',
                'clock_out' => '',
                'breaks' => [],
                'comment' => '',
            ];

            // 既存の管理者用Bladeで詳細画面を表示する
            return view('admin.admin-detail', compact(
                'attendanceRecord',
                'user'
            ));
        }

        // 指定された勤怠を休憩情報と一緒に取得する
        $attendance = AttendanceRecord::with('breaks')
            ->findOrFail($id);

        // 勤怠に紐づくユーザーを取得する
        $user = $attendance->user;

        // 休憩データを表示用の配列に変換する
        $breaks = $attendance->breaks->map(function (AttendanceBreak $break): array {

            // 休憩開始・終了時刻を整形する
            return [
                'break_in' => $break->break_in
                    ? Carbon::parse($break->break_in)->format('H:i')
                    : '',

                'break_out' => $break->break_out
                    ? Carbon::parse($break->break_out)->format('H:i')
                    : '',
            ];

        })->toArray();

        // Bladeに渡す勤怠詳細データを作成する
        $attendanceRecord = [
            'id' => $attendance->id,

            'year' => Carbon::parse($attendance->date)
                ->format('Y年'),

            'date' => Carbon::parse($attendance->date)
                ->format('m月d日'),

            'clock_in' => $attendance->clock_in
                ? Carbon::parse($attendance->clock_in)->format('H:i')
                : '',

            'clock_out' => $attendance->clock_out
                ? Carbon::parse($attendance->clock_out)->format('H:i')
                : '',

            'breaks' => $breaks,

            'comment' => $attendance->comment ?? '',
        ];

        // 管理者用勤怠詳細画面を表示する
        return view('admin.admin-detail', compact(
            'attendanceRecord',
            'user'
        ));
    }

    /**
     * 管理者が勤怠情報を登録・修正する。
     *
     * @param AttendanceCorrectionRequest $request バリデーション済みの入力
     * @param int|string $id 勤怠IDまたはスタッフIDと日付
     * @return RedirectResponse 保存後の勤怠詳細画面
     */
    public function update(
        AttendanceCorrectionRequest $request,
        int|string $id
    ): RedirectResponse {

        // バリデーション済みの入力値を取得する
        $validated = $request->validated();

        // 数値IDの場合は既存の勤怠を更新する
        if (ctype_digit((string) $id)) {

            // 指定された勤怠データを取得する
            $attendance = AttendanceRecord::findOrFail($id);

            // Modelのメソッドで勤怠を更新する
            $attendance->updateAttendance($validated);

            // 更新後の詳細画面に戻る
            return redirect('/admin/attendance/' . $id);
        }

        // スタッフIDと日付を分離する
        [$userId, $dateString] = explode('-', (string) $id, 2);

        // 日付を厳密に解析する
        $parsedDate = \DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $dateString
        );

        // 不正な日付の場合は404エラーにする
        abort_unless(
            $parsedDate !== false
                && $parsedDate->format('Y-m-d') === $dateString,
            404
        );

        // 保存後の勤怠IDを格納する変数を用意する
        $attendanceId = null;

        // 新規登録と勤怠更新をまとめて処理する
        DB::transaction(function () use (
            $userId,
            $dateString,
            $validated,
            &$attendanceId
        ): void {

            // 対象スタッフをロックして取得する
            $user = User::whereKey((int) $userId)
                ->lockForUpdate()
                ->firstOrFail();

            // 同じ日付の勤怠がすでに存在するか確認する
            $attendance = AttendanceRecord::where(
                'user_id',
                $user->id
            )
                ->whereDate('date', $dateString)
                ->first();

            // 勤怠レコードがまだ存在しない場合
            if (!$attendance) {

                // 出退勤が空欄の勤怠レコードを新規作成する
                $attendance = AttendanceRecord::create([
                    'user_id' => $user->id,
                    'date' => $dateString,
                    'clock_in' => null,
                    'clock_out' => null,
                ]);
            }

            // Modelのメソッドで出退勤・休憩を保存する
            $attendance->updateAttendance($validated);

            // 保存された勤怠IDを取得する
            $attendanceId = $attendance->id;
        });

        // 新規登録した勤怠の詳細画面に移動する
        return redirect('/admin/attendance/' . $attendanceId);
    }

    /**
     * 管理者用の勤怠修正申請一覧画面を表示する。
     */
    public function applicationList(): View
    {
        // 修正申請を関連データと一緒に取得する
        $applications = AttendanceCorrect::withCorrectionDetails()

            // 申請日時が新しい順に並べる
            ->latest('requested_at')

            // 申請データを取得する
            ->get();

        // 修正申請一覧画面を表示する
        return view('admin.admin-application-list', compact(
            'applications'
        ));
    }

    /**
     * 指定された勤怠修正申請の詳細画面を表示する。
     */
    public function showApplication(int $id): View
    {
        // 指定された修正申請を関連データと一緒に取得する
        $application = AttendanceCorrect::with([
            'attendanceRecord.user',
            'breakCorrects',
        ])->findOrFail($id);

        // 申請者を取得する
        $user = $application->attendanceRecord->user;

        // 修正申請詳細画面を表示する
        return view('admin.admin-application-detail', compact(
            'application',
            'user'
        ));
    }

    /**
     * 指定された勤怠修正申請を承認する。
     */
    public function approveApplication(int $id): RedirectResponse
    {
        // 勤怠データと承認状態をまとめて更新する
        DB::transaction(function () use ($id): void {

            // 二重承認を防ぐため申請データをロックして取得する
            $application = AttendanceCorrect::with('breakCorrects')
                ->lockForUpdate()
                ->findOrFail($id);

            // すでに承認済みの場合は処理しない
            if ($application->approval_status === '承認済み') {
                return;
            }

            // 修正対象の勤怠データを取得する
            $attendance = $application->attendanceRecord;

            // 修正申請に紐づく休憩データを取得する
            $breaks = $application->breakCorrects;

            // 勤怠更新用のデータを作成する
            $validated = [

                // 修正申請の出勤時刻をH:i形式にする
                'new_clock_in' => Carbon::parse(
                    $application->clock_in
                )->format('H:i'),

                // 修正申請の退勤時刻をH:i形式にする
                'new_clock_out' => Carbon::parse(
                    $application->clock_out
                )->format('H:i'),

                // 申請された休憩開始時刻を配列に変換する
                'new_break_in' => $breaks->map(function ($break) {

                    // 休憩開始時刻をH:i形式で返す
                    return $break->break_in
                        ? Carbon::parse($break->break_in)->format('H:i')
                        : null;

                })->all(),

                // 申請された休憩終了時刻を配列に変換する
                'new_break_out' => $breaks->map(function ($break) {

                    // 休憩終了時刻をH:i形式で返す
                    return $break->break_out
                        ? Carbon::parse($break->break_out)->format('H:i')
                        : null;

                })->all(),
            ];

            // Modelのメソッドで出勤・退勤・休憩をまとめて更新する
            $attendance->updateAttendance($validated);

            // 承認状態を承認済みに変更する
            $application->approval_status = true;

            // 承認日時を保存する
            $application->approved_at = now();

            // 承認情報をDBに保存する
            $application->save();
        });

        // 承認後に申請詳細画面へ戻る
        return redirect(
            '/stamp_correction_request/approve/' . $id
        );
    }
}
