<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceBreak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\AttendanceCorrect;
use App\Http\Requests\AttendanceCorrectionRequest;

class AttendanceController extends Controller
{
    // 一般ユーザー勤怠打刻画面
    public function index()
    {
        $user = Auth::user();

        $attendanceRecord = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('date', today())
            ->first();

        if (!$attendanceRecord) {
            $attendanceStatus = '勤務外';
        } else {
            $attendanceStatus = $attendanceRecord->getAttendanceStatus();
        }

        // Blade が参照するユーザーのステータスに設定
        $user->attendance_status = $attendanceStatus;

        $weekdays = ['日', '月', '火', '水', '木', '金', '土'];

        $formattedDate = Carbon::now()->format('Y年n月j日')
            . '(' . $weekdays[Carbon::now()->dayOfWeek] . ')';

        $formattedTime = Carbon::now()->format('H:i');

        return view('user.attendance-register', compact(
            'user',
            'formattedDate',
            'formattedTime'
        ));
    }

    // 勤怠打刻処理
    public function store(Request $request)
        {
            $user = Auth::user();

            $action = $request->input('action');

            $attendanceRecord = AttendanceRecord::where('user_id', $user->id)
                ->whereDate('date', today())
                ->first();

            // 出勤
            if ($action === 'clock_in') {
                // 当日の勤怠レコードがなければ出勤記録を作成
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
                if ($attendanceRecord) {
                    $attendanceRecord->update([
                        'clock_out' => now()->format('H:i:s'),
                    ]);
                }
            }

            // 休憩入
            elseif ($action === 'break_in') {
                if ($attendanceRecord) {
                    AttendanceBreak::create([
                        'attendance_record_id' => $attendanceRecord->id,
                        'break_in' => now()->format('H:i:s'),
                    ]);
                }
            }

            // 休憩戻
            elseif ($action === 'break_out') {
                if ($attendanceRecord) {
                    $break = AttendanceBreak::where(
                        'attendance_record_id',
                        $attendanceRecord->id
                    )
                        ->whereNull('break_out')
                        ->latest()
                        ->first();

                    if ($break) {
                        $break->update([
                            'break_out' => now()->format('H:i:s'),
                        ]);
                    }
                }
            }

            return redirect('/attendance');
        }
    
    // 勤怠一覧
    public function list(Request $request)
        {
            $user = Auth::user();

            $date = $request->date
                ? Carbon::parse($request->date)
                : Carbon::today();

            $previousMonth = $date->copy()
                ->subMonth()
                ->format('Y-m');

            $nextMonth = $date->copy()
                ->addMonth()
                ->format('Y-m');

            $attendanceRecords = AttendanceRecord::with('breaks')
                ->where('user_id', $user->id)
                ->whereYear('date', $date->year)
                ->whereMonth('date', $date->month)
                ->get();

            $formattedAttendanceRecords = $attendanceRecords->map(function ($attendance) {

                $totalBreakMinutes = 0;

                foreach ($attendance->breaks as $break) {
                    if ($break->break_in && $break->break_out) {
                        $breakIn = Carbon::parse($break->break_in);
                        $breakOut = Carbon::parse($break->break_out);

                        $totalBreakMinutes += $breakIn->diffInMinutes($breakOut);
                    }
                }

                $totalBreakTime = Carbon::createFromTime(0, 0)
                    ->addMinutes($totalBreakMinutes);

                $totalTime = null;

                if ($attendance->clock_in && $attendance->clock_out) {
                    $clockIn = Carbon::parse($attendance->clock_in);
                    $clockOut = Carbon::parse($attendance->clock_out);

                    $totalWorkMinutes = $clockIn->diffInMinutes($clockOut)
                        - $totalBreakMinutes;

                    $totalTime = Carbon::createFromTime(0, 0)
                        ->addMinutes($totalWorkMinutes);
                }

                return [
                    'id' => $attendance->id,
                    'date' => Carbon::parse($attendance->date)->format('m/d'),
                    'clock_in' => $attendance->clock_in
                        ? Carbon::parse($attendance->clock_in)->format('H:i')
                        : '',
                    'clock_out' => $attendance->clock_out
                        ? Carbon::parse($attendance->clock_out)->format('H:i')
                        : '',
                    'total_break_time' => $totalBreakTime,
                    'total_time' => $totalTime,
                ];
            })->toArray();

            return view('user.user-attendance-list', compact(
                'date',
                'previousMonth',
                'nextMonth',
                'formattedAttendanceRecords'
            ));
        }

    // 勤怠詳細画面
    public function show(int $id)
    {
        $user = Auth::user();

        // ログイン中のユーザー本人の勤怠だけ取得する
        $attendanceRecord = AttendanceRecord::with('breaks')
            ->where('user_id', $user->id)
            ->findOrFail($id);

        // 承認待ちの修正申請を取得する
        // status: false = 承認待ち、true = 承認済み
        $application = AttendanceCorrect::with('breakCorrects')
            ->where('attendance_record_id', $attendanceRecord->id)
            ->where('status', false)
            ->latest()
            ->first();

        // 承認待ち申請がある場合は申請内容、なければ元の勤怠を表示する
        $displayClockIn = $application
            ? $application->clock_in
            : $attendanceRecord->clock_in;

        $displayClockOut = $application
            ? $application->clock_out
            : $attendanceRecord->clock_out;

        $displayMemo = $application
            ? $application->comment
            : '';

        if ($application) {
            $breaks = $application->breakCorrects->map(function ($break) {
                return [
                    'break_in' => $break->break_in
                        ? Carbon::parse($break->break_in)->format('H:i')
                        : '',
                    'break_out' => $break->break_out
                        ? Carbon::parse($break->break_out)->format('H:i')
                        : '',
                ];
            })->toArray();
        } else {
            $breaks = $attendanceRecord->breaks->map(function ($break) {
                return [
                    'break_in' => $break->break_in
                        ? Carbon::parse($break->break_in)->format('H:i')
                        : '',
                    'break_out' => $break->break_out
                        ? Carbon::parse($break->break_out)->format('H:i')
                        : '',
                ];
            })->toArray();
        }

        $date = Carbon::parse($attendanceRecord->date);

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

        return view('user.user-detail', compact('user', 'data'));
    }

    // 勤怠修正申請
    public function requestCorrection(
        AttendanceCorrectionRequest $request,
        int $id
    ) {
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

        if ($hasPendingApplication) {
            return redirect('/attendance/detail/' . $id);
        }

        $validated = $request->validated();

        // 勤怠修正申請を保存
        $application = AttendanceCorrect::create([
            'attendance_record_id' => $attendanceRecord->id,
            'clock_in' => $validated['new_clock_in'],
            'clock_out' => $validated['new_clock_out'],
            'comment' => $validated['comment'],
            'status' => false,
            'requested_at' => now(),
        ]);

        // 休憩の修正申請を保存
        $breakIns = $validated['new_break_in'] ?? [];
        $breakOuts = $validated['new_break_out'] ?? [];

        foreach ($breakIns as $index => $breakIn) {
            $breakOut = $breakOuts[$index] ?? null;

            // 開始・終了が両方空なら保存しない
            if (blank($breakIn) && blank($breakOut)) {
                continue;
            }

            $application->breakCorrects()->create([
                'break_in' => $breakIn ?: null,
                'break_out' => $breakOut ?: null,
            ]);
        }

        return redirect('/attendance/detail/' . $id);
    }

    // 申請一覧画面
    public function correctionRequestList()
    {
        $user = Auth::user();

        $formattedApplications =
            AttendanceCorrect::getFormattedApplications($user->id);

        return view('user.user-application-list', compact(
            'user',
            'formattedApplications'
        ));
    }
}