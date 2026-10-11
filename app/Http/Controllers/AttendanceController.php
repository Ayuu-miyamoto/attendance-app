<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceBreak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    // 
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

    // 
    public function store(Request $request)
        {
            $user = Auth::user();

            $action = $request->input('action');

            $attendanceRecord = AttendanceRecord::where('user_id', $user->id)
                ->whereDate('date', today())
                ->first();

            // 出勤
            if ($action === 'clock_in') {
                AttendanceRecord::create([
                    'user_id' => $user->id,
                    'date' => today(),
                    'clock_in' => now()->format('H:i:s'),
                ]);
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
}