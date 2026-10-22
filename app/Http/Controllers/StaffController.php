<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    // スタッフ一覧
    public function index()
    {
        $users = User::orderBy('id')->get();

        return view('admin.staff-list', compact('users'));
    }

    // スタッフ別勤怠一覧
    public function show(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $date = Carbon::parse(
            $request->query('date', now()->format('Y-m'))
        )->startOfMonth();

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        $records = AttendanceRecord::with('breaks')
            ->where('user_id', $user->id)
            ->whereBetween('date', [
                $date->copy()->startOfMonth()->toDateString(),
                $date->copy()->endOfMonth()->toDateString(),
            ])
            ->orderBy('date')
            ->get();

        $formattedAttendanceRecords = $records->map(function ($record) {
            $recordDate = Carbon::parse($record->date);

            return [
                'id' => $record->id,
                'date' => $recordDate->format('Y/m/d')
                    . '(' . ['日', '月', '火', '水', '木', '金', '土'][$recordDate->dayOfWeek] . ')',
                'clock_in' => $record->clock_in
                    ? Carbon::parse($record->clock_in)->format('H:i')
                    : '',
                'clock_out' => $record->clock_out
                    ? Carbon::parse($record->clock_out)->format('H:i')
                    : '',
                'total_break_time' => $record->formatMinutes(
                    $record->total_break_minutes
                ),
                'total_time' => $record->formatMinutes(
                    $record->total_work_minutes
                ),
            ];
        })->toArray();

        return view('admin.staff-attendance-list', compact(
            'user',
            'date',
            'previousMonth',
            'nextMonth',
            'formattedAttendanceRecords'
        ));
    }
}