<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminAttendanceController extends Controller
{
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
}