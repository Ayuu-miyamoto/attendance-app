<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\AttendanceRecord;
use App\Models\AttendanceBreak;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --------------------------------
        // ユーザー情報
        // --------------------------------

        // user1を作成（一般ユーザー）
        $user1 = User::create([
            'name' => 'user1',
            'email' => 'user1@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);

        // user2を作成（一般ユーザー）
        $user2 = User::create([
            'name' => 'user2',
            'email' => 'user2@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);

        // user3を作成（管理者）
        $user3 = User::create([
            'name' => 'user3',
            'email' => 'user3@example.com',
            'password' => bcrypt('password'),
            'admin_status' => true,
            'email_verified_at' => now(),
        ]);

        // --------------------------------
        // 集計の基準月
        // --------------------------------

        // 2026年11月を当月として固定する
        $currentMonth = Carbon::create(2026, 11, 1);

        // --------------------------------
        // user1：過去5ヶ月（6月〜10月）
        // --------------------------------

        // 5ヶ月前から1ヶ月前まで繰り返す
        for ($month = 5; $month >= 1; $month--) {

            // 対象月の月初を取得する
            $date = $currentMonth->copy()
                ->subMonths($month)
                ->startOfMonth();

            // 登録した勤務日数を数える
            $count = 0;

            // 各月15日分の勤怠を作成する
            while ($count < 15) {

                // 平日の場合だけ登録する
                if ($date->isWeekday()) {

                    // 通常勤務の勤怠を作成する
                    $attendance = AttendanceRecord::create([
                        'user_id' => $user1->id,
                        'date' => $date->format('Y-m-d'),
                        'clock_in' => '09:00:00',
                        'clock_out' => '18:00:00',
                    ]);

                    // 固定休憩を登録する
                    AttendanceBreak::create([
                        'attendance_record_id' => $attendance->id,
                        'break_in' => '12:00:00',
                        'break_out' => '13:00:00',
                    ]);

                    // 登録日数を増やす
                    $count++;
                }

                // 翌日に進む
                $date->addDay();
            }
        }

        // --------------------------------
        // user1：2026年11月（17日）
        // --------------------------------

        // 11月1日から開始する
        $date = $currentMonth->copy();

        // 登録した勤務日数を数える
        $count = 0;

        // 17日分の勤怠を作成する
        while ($count < 17) {

            // 平日の場合だけ登録する
            if ($date->isWeekday()) {

                // 通常勤務10日
                if ($count < 10) {
                    $clockIn = '09:00:00';
                    $clockOut = '18:00:00';

                // 残業3日
                } elseif ($count < 13) {
                    $clockIn = '09:00:00';
                    $clockOut = '20:00:00';

                // 遅刻2日
                } elseif ($count < 15) {
                    $clockIn = '09:30:00';
                    $clockOut = '18:00:00';

                // 早退1日
                } elseif ($count < 16) {
                    $clockIn = '09:00:00';
                    $clockOut = '17:00:00';

                // 長時間労働1日
                } else {
                    $clockIn = '08:00:00';
                    $clockOut = '21:00:00';
                }

                // 勤怠を登録する
                $attendance = AttendanceRecord::create([
                    'user_id' => $user1->id,
                    'date' => $date->format('Y-m-d'),
                    'clock_in' => $clockIn,
                    'clock_out' => $clockOut,
                ]);

                // 固定休憩を登録する
                AttendanceBreak::create([
                    'attendance_record_id' => $attendance->id,
                    'break_in' => '12:00:00',
                    'break_out' => '13:00:00',
                ]);

                // 登録日数を増やす
                $count++;
            }

            // 翌日に進む
            $date->addDay();
        }

        // --------------------------------
        // user2：2026年11月（17日）
        // --------------------------------

        // 11月1日から開始する
        $date = $currentMonth->copy();

        // 登録した勤務日数を数える
        $count = 0;

        // 17日分の勤怠を作成する
        while ($count < 17) {

            // 平日の場合だけ登録する
            if ($date->isWeekday()) {

                // 通常勤務の勤怠を作成する
                $attendance = AttendanceRecord::create([
                    'user_id' => $user2->id,
                    'date' => $date->format('Y-m-d'),
                    'clock_in' => '09:00:00',
                    'clock_out' => '18:00:00',
                ]);

                // 休憩を登録する
                AttendanceBreak::create([
                    'attendance_record_id' => $attendance->id,
                    'break_in' => '12:00:00',
                    'break_out' => '13:00:00',
                ]);

                // 登録日数を増やす
                $count++;
            }

            // 翌日に進む
            $date->addDay();
        }

        // --------------------------------
        // user3：2026年11月（17日）
        // --------------------------------

        // 11月1日から開始する
        $date = $currentMonth->copy();

        // 登録した勤務日数を数える
        $count = 0;

        // 17日分の勤怠を作成する
        while ($count < 17) {

            // 平日の場合だけ登録する
            if ($date->isWeekday()) {

                // 通常勤務の勤怠を作成する
                $attendance = AttendanceRecord::create([
                    'user_id' => $user3->id,
                    'date' => $date->format('Y-m-d'),
                    'clock_in' => '09:00:00',
                    'clock_out' => '18:00:00',
                ]);

                // 休憩を登録する
                AttendanceBreak::create([
                    'attendance_record_id' => $attendance->id,
                    'break_in' => '12:00:00',
                    'break_out' => '13:00:00',
                ]);

                // 登録日数を増やす
                $count++;
            }

            // 翌日に進む
            $date->addDay();
        }
    }
}
