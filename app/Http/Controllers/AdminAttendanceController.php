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
     *
     * @param Request $request 表示対象の日付を含むリクエスト
     * @return View 管理者用勤怠一覧画面
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
     * @param int $id 勤怠レコードのID
     * @return View 管理者用勤怠詳細画面
     */
    public function show(int $id): View
    {
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
     * 管理者が勤怠情報を修正する。
     *
     * @param AttendanceCorrectionRequest $request バリデーション済みの修正内容
     * @param int $id 修正対象の勤怠レコードID
     * @return RedirectResponse 勤怠詳細画面へのリダイレクト
     */
    public function update(
        AttendanceCorrectionRequest $request,
        int $id
    ): RedirectResponse {
        // 指定された勤怠データを取得する
        $attendance = AttendanceRecord::findOrFail($id);

        // Modelのメソッドを呼び出して勤怠を更新する
        $attendance->updateAttendance($request->validated());

        // 更新後の勤怠詳細画面に戻る
        return redirect('/admin/attendance/' . $id);
    }

    /**
     * 管理者用の勤怠修正申請一覧画面を表示する。
     *
     * @return View 修正申請一覧画面
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
     *
     * @param int $id 修正申請のID
     * @return View 修正申請詳細画面
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
     *
     * @param int $id 修正申請のID
     * @return RedirectResponse 承認画面へのリダイレクト
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
