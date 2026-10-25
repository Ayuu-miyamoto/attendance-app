<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\AttendanceCorrect;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\AttendanceCorrectionRequest;

class AdminAttendanceController extends Controller
{
    // 管理者用勤怠一覧
    public function index(Request $request)
    {
        $date = $request->date
            ? Carbon::parse($request->date)
            : Carbon::today();

        $previousDay = $date->copy()->subDay()->format('Y-m-d');
        $nextDay = $date->copy()->addDay()->format('Y-m-d');

        $users = User::where('admin_status', false)->get();

        $attendanceRecords = AttendanceRecord::with('breaks')
            ->whereDate('date', $date)
            ->get();

        foreach ($attendanceRecords as $attendance) {

            $totalBreakMinutes = 0;

            foreach ($attendance->breaks as $break) {
                if ($break->break_in && $break->break_out) {
                    $breakIn = Carbon::parse($break->break_in);
                    $breakOut = Carbon::parse($break->break_out);

                    $totalBreakMinutes += $breakIn->diffInMinutes($breakOut);
                }
            }

            $attendance->total_break_time = Carbon::createFromTime(0, 0)
                ->addMinutes($totalBreakMinutes);

            if ($attendance->clock_in && $attendance->clock_out) {

                $clockIn = Carbon::parse($attendance->clock_in);
                $clockOut = Carbon::parse($attendance->clock_out);

                $totalWorkMinutes = $clockIn->diffInMinutes($clockOut)
                    - $totalBreakMinutes;

                $attendance->total_time = Carbon::createFromTime(0, 0)
                    ->addMinutes($totalWorkMinutes);
            }
        }

        return view('admin.admin-attendance-list', compact(
            'date',
            'previousDay',
            'nextDay',
            'users',
            'attendanceRecords'
        ));
    }

    // 勤怠詳細画面
    public function show($id)
        {
            $attendance = AttendanceRecord::with('breaks')->findOrFail($id);

            $user = User::findOrFail($attendance->user_id);

            $breaks = $attendance->breaks->map(function ($break) {
                return [
                    'break_in' => $break->break_in
                        ? Carbon::parse($break->break_in)->format('H:i')
                        : '',
                    'break_out' => $break->break_out
                        ? Carbon::parse($break->break_out)->format('H:i')
                        : '',
                ];
            })->toArray();

            $attendanceRecord = [
                'id' => $attendance->id,
                'year' => Carbon::parse($attendance->date)->format('Y年'),
                'date' => Carbon::parse($attendance->date)->format('m月d日'),
                'clock_in' => $attendance->clock_in
                    ? Carbon::parse($attendance->clock_in)->format('H:i')
                    : '',
                'clock_out' => $attendance->clock_out
                    ? Carbon::parse($attendance->clock_out)->format('H:i')
                    : '',
                'breaks' => $breaks,
                'comment' => '',
            ];

            return view('admin.admin-detail', compact(
                'attendanceRecord',
                'user'
            ));
        }

    /**
     * 管理者による勤怠修正処理
     */
    public function update(AttendanceCorrectionRequest $request, int $id)
    {
        // バリデーション済みの入力値を取得する
        $validated = $request->validated();

        // 指定された勤怠データを取得する
        $attendance = AttendanceRecord::findOrFail($id);

        // 勤怠と休憩をまとめて更新する
        DB::transaction(function () use ($attendance, $validated) {

            // 出勤・退勤時刻を秒まで含む形式に変換して更新する
            $attendance->update([

                // 出勤時刻を「09:30」から「09:30:00」に変換する
                'clock_in' => $validated['new_clock_in'] . ':00',

                // 退勤時刻を「18:30」から「18:30:00」に変換する
                'clock_out' => $validated['new_clock_out'] . ':00',
            ]);

            // 既存の休憩データを削除する
            $attendance->breaks()->delete();

            // 入力された休憩開始時刻を取得する
            $breakIns = $validated['new_break_in'] ?? [];

            // 入力された休憩終了時刻を取得する
            $breakOuts = $validated['new_break_out'] ?? [];

            // 休憩データを1件ずつ処理する
            foreach ($breakIns as $index => $breakIn) {

                // 同じ番号の休憩終了時刻を取得する
                $breakOut = $breakOuts[$index] ?? null;

                // 開始・終了が両方空欄の場合は保存しない
                if (blank($breakIn) && blank($breakOut)) {
                    continue;
                }

                // 休憩データを新しく保存する
                $attendance->breaks()->create([

                    // 休憩開始時刻を「12:00」から「12:00:00」に変換する
                    // 未入力の場合はnullを保存する
                    'break_in' => filled($breakIn) ? $breakIn . ':00' : null,

                    // 休憩終了時刻を「13:00」から「13:00:00」に変換する
                    // 未入力の場合はnullを保存する
                    'break_out' => filled($breakOut) ? $breakOut . ':00' : null,
                ]);
            }
        });

        // 更新後は管理者用の勤怠詳細画面に戻る
        return redirect('/admin/attendance/' . $id);
    }
        
    // 修正申請一覧
    public function applicationList()
    {
        // 承認待ち・承認済みを含むすべての申請を取得する
        $applications = AttendanceCorrect::withCorrectionDetails()

            // 申請日時が新しい順に並べる
            ->latest('requested_at')

            // データベースから申請一覧を取得する
            ->get();

        // 申請一覧画面にデータを渡す
        return view('admin.admin-application-list', compact(
            'applications'
        ));
    }

    // 修正申請詳細
    public function showApplication($id)
    {
        $application = AttendanceCorrect::with([
            'attendanceRecord.user',
            'breakCorrects',
        ])->findOrFail($id);

        $user = $application->attendanceRecord->user;

        return view('admin.admin-application-detail', compact(
            'application',
            'user'
        ));
    }

    // 修正申請を承認
    public function approveApplication($id)
    {
        $application = AttendanceCorrect::findOrFail($id);

        if ($application->approval_status === '承認待ち') {
            $application->approval_status = true;
            $application->approved_at = now();
            $application->save();
        }

        return redirect(
            '/stamp_correction_request/approve/' . $application->id
        );
    }
}