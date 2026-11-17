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
        // ID順にすべてのユーザーを取得する
        $users = User::orderBy('id')->get();

        // スタッフ一覧画面を表示する
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
        // 指定されたスタッフを取得する
        $user = User::findOrFail($id);

        // 表示対象の年月を取得する
        $date = Carbon::parse(
            $request->query('date', now()->format('Y-m'))
        )->startOfMonth();

        // 表示する月の初日を取得する
        $startDate = $date->copy()->startOfMonth();

        // 表示する月の末日を取得する
        $endDate = $date->copy()->endOfMonth();

        // 前月の年月を取得する
        $previousMonth = $date->copy()
            ->subMonthNoOverflow()
            ->format('Y-m');

        // 翌月の年月を取得する
        $nextMonth = $date->copy()
            ->addMonthNoOverflow()
            ->format('Y-m');

        // 指定スタッフの対象月の勤怠を休憩情報と一緒に取得する
        $records = AttendanceRecord::with('breaks')
            ->where('user_id', $user->id)
            ->whereBetween('date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->orderBy('date')
            ->get()

            // 日付をキーにして勤怠データを検索できるようにする
            ->keyBy(function (AttendanceRecord $record): string {

                // 勤怠日付をYYYY-MM-DD形式で返す
                return $record->date->format('Y-m-d');
            });

        // 日本語の曜日を定義する
        $weekdays = ['日', '月', '火', '水', '木', '金', '土'];

        // Bladeに渡す勤怠一覧用の配列を用意する
        $formattedAttendanceRecords = [];

        // 月初から月末まで1日ずつ処理する
        for (
            $day = $startDate->copy();
            $day->lte($endDate);
            $day->addDay()
        ) {

            // 処理中の日付をYYYY-MM-DD形式で取得する
            $dayString = $day->format('Y-m-d');

            // その日の勤怠レコードを取得する
            $record = $records->get($dayString);

            // 日付と曜日を表示用の文字列に変換する
            $formattedDate = $day->format('Y/m/d')
                . '(' . $weekdays[$day->dayOfWeek] . ')';

            // 勤怠レコードが存在しない場合
            if (!$record) {

                // 日付だけを表示する空欄の勤怠情報を追加する
                $formattedAttendanceRecords[] = [
                    'id' => null,
                    'date' => $formattedDate,
                    'clock_in' => '',
                    'clock_out' => '',
                    'total_break_time' => null,
                    'total_time' => null,
                ];

                // 次の日付の処理に進む
                continue;
            }

            // 合計休憩時間を取得する
            $totalBreakMinutes = $record->total_break_minutes;

            // 合計実働時間を取得する
            $totalWorkMinutes = $record->total_work_minutes;

            // 休憩時間の初期値を空欄にする
            $totalBreakTime = null;

            // 休憩時間が存在する場合
            if ($totalBreakMinutes > 0) {

                // 合計休憩時間を表示用の形式に変換する
                $totalBreakTime = $record->formatMinutes(
                    $totalBreakMinutes
                );
            }

            // 実働時間の初期値を空欄にする
            $totalTime = null;

            // 出勤・退勤時刻が両方登録されている場合
            if ($record->clock_in && $record->clock_out) {

                // 合計実働時間を表示用の形式に変換する
                $totalTime = $record->formatMinutes(
                    $totalWorkMinutes
                );
            }

            // 勤怠が存在する日の情報を配列に追加する
            $formattedAttendanceRecords[] = [
                'id' => $record->id,

                'date' => $formattedDate,

                'clock_in' => $record->clock_in
                    ? Carbon::parse($record->clock_in)->format('H:i')
                    : '',

                'clock_out' => $record->clock_out
                    ? Carbon::parse($record->clock_out)->format('H:i')
                    : '',

                'total_break_time' => $totalBreakTime,

                'total_time' => $totalTime,
            ];
        }

        // スタッフ別の月次勤怠一覧画面を表示する
        return view('admin.staff-attendance-list', compact(
            'user',
            'date',
            'previousMonth',
            'nextMonth',
            'formattedAttendanceRecords'
        ));
    }

    /**
     * スタッフの月次勤怠をCSVで出力する。
     */
    public function export(Request $request)
    {
        // 入力されたスタッフIDと対象年月を検証する
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'year_month' => ['required', 'date_format:Y-m'],
        ]);

        // Modelから対象月の勤怠を取得する
        $records = AttendanceRecord::getMonthlyRecords(
            $validated['user_id'],
            $validated['year_month']
        );

        // CSVのファイル名を設定する
        $fileName = "attendance_{$validated['user_id']}_{$validated['year_month']}.csv";

        // CSVファイルをダウンロードする
        return response()->streamDownload(function () use ($records) {

            // CSVの出力先を開く
            $stream = fopen('php://output', 'w');

            // Excelで日本語が文字化けしないようにする
            fwrite($stream, "\xEF\xBB\xBF");

            // CSVの見出しを出力する
            fputcsv($stream, ['日付', '出勤', '退勤', '休憩', '合計']);

            // 勤怠情報をCSVに出力する
            foreach ($records as $record) {

                // 勤怠レコードをCSV用の配列に変換して出力する
                fputcsv($stream, $record->toCsvRow());
            }

            // 出力先を閉じる
            fclose($stream);

        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}