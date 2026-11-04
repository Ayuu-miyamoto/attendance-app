<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffController extends Controller
{
    /**
     * 管理者用のスタッフ一覧画面を表示する。
     *
     * @return View スタッフ一覧画面
     */
    public function index(): View
    {
        $users = User::orderBy('id')->get();

        return view('admin.staff-list', compact('users'));
    }

    /**
     * 指定されたスタッフの月次勤怠一覧画面を表示する。
     *
     * @param Request $request 表示対象の年月を含むリクエスト
     * @param int $id スタッフのユーザーID
     * @return View スタッフ別勤怠一覧画面
     */
    public function show(Request $request, int $id): View
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

        $formattedAttendanceRecords = $records->map(function (AttendanceRecord $record): array {
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