<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\AttendanceCorrect;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminAttendanceController extends Controller
{
    /**
     * 管理者用勤怠一覧
     */
    public function index(Request $request)
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

        // 勤怠データごとに表示用の時間を設定する
        foreach ($attendanceRecords as $attendance) {

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
        }

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
     * 管理者用勤怠詳細
     */
    public function show($id)
    {
        // 指定された勤怠を休憩情報と一緒に取得する
        $attendance = AttendanceRecord::with('breaks')
            ->findOrFail($id);

        // 勤怠に紐づくユーザーを取得する
        $user = $attendance->user;

        // 休憩データを表示用の配列に変換する
        $breaks = $attendance->breaks->map(function ($break) {

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

            'comment' => '',
        ];

        // 管理者用勤怠詳細画面を表示する
        return view('admin.admin-detail', compact(
            'attendanceRecord',
            'user'
        ));
    }

    /**
     * 管理者による勤怠修正
     */
    public function update(
        AttendanceCorrectionRequest $request,
        int $id
    ) {
        // 指定された勤怠データを取得する
        $attendance = AttendanceRecord::findOrFail($id);

        // Modelのメソッドを呼び出して勤怠を更新する
        $attendance->updateAttendance($request->validated());

        // 更新後の勤怠詳細画面に戻る
        return redirect('/admin/attendance/' . $id);
    }

    /**
     * 管理者用修正申請一覧
     */
    public function applicationList()
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
     * 修正申請詳細
     */
    public function showApplication($id)
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
     * 修正申請の承認処理
     */
    public function approveApplication($id)
    {
        // 指定された修正申請を取得する
        $application = AttendanceCorrect::findOrFail($id);

        // 承認待ちの場合のみ処理する
        if (!$application->approval_status) {

            // 承認済みに変更する
            $application->approval_status = true;

            // 承認日時を設定する
            $application->approved_at = now();

            // 変更内容を保存する
            $application->save();
        }

        // 承認画面に戻る
        return redirect(
            '/stamp_correction_request/approve/' . $application->id
        );
    }
}