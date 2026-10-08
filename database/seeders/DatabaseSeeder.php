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
        // user1を作成
        $user1 = User::create([
            'name' => 'user1',
            'email' => 'user1@example.com',
            'password' => bcrypt('password'),
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);

        // user2を作成
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
        // user1 過去5ヶ月：各月の平日15日
        // --------------------------------

        for ($month = 5; $month >= 1; $month--) {

            $date = Carbon::create(2026, 10, 1)
                ->subMonths($month)
                ->startOfMonth();

            $count = 0;

            while ($count < 15) {

                if ($date->isWeekday()) {

                    $attendance = AttendanceRecord::create([
                        'user_id' => $user1->id,
                        'date' => $date->format('Y-m-d'),
                        'clock_in' => '09:00:00',
                        'clock_out' => '18:00:00',
                    ]);

                    // 休憩
                    AttendanceBreak::create([
                        'attendance_record_id' => $attendance->id,
                        'break_in' => '12:00:00',
                        'break_out' => '13:00:00',
                    ]);

                    $count++;
                }

                $date->addDay();
            }
        }

        // --------------------------------
        // user1 2026年9月：17日分
        // --------------------------------

        $september = Carbon::create(2026, 9, 1);

        $count = 0;

        while ($count < 17) {

            if ($september->isWeekday()) {

                // 勤務パターン
                if ($count < 10) {
                    // 通常 10日
                    $clockIn = '09:00:00';
                    $clockOut = '18:00:00';

                } elseif ($count < 13) {
                    // 残業 3日
                    $clockIn = '09:00:00';
                    $clockOut = '20:00:00';

                } elseif ($count < 15) {
                    // 遅刻 2日
                    $clockIn = '09:30:00';
                    $clockOut = '18:00:00';

                } elseif ($count < 16) {
                    // 早退 1日
                    $clockIn = '09:00:00';
                    $clockOut = '17:00:00';

                } else {
                    // 長時間労働 1日
                    $clockIn = '08:00:00';
                    $clockOut = '21:00:00';
                }

                $attendance = AttendanceRecord::create([
                    'user_id' => $user1->id,
                    'date' => $september->format('Y-m-d'),
                    'clock_in' => $clockIn,
                    'clock_out' => $clockOut,
                ]);

                // 休憩
                AttendanceBreak::create([
                    'attendance_record_id' => $attendance->id,
                    'break_in' => '12:00:00',
                    'break_out' => '13:00:00',
                ]);

                $count++;
            }

            $september->addDay();
        }
        // --------------------------------
        // user2：2026年9月 17日分
        // --------------------------------

        $date = Carbon::create(2026, 9, 1);
        $count = 0;

        while ($count < 17) {

            if ($date->isWeekday()) {

                $attendance = AttendanceRecord::create([
                    'user_id' => $user2->id,
                    'date' => $date->format('Y-m-d'),
                    'clock_in' => '09:00:00',
                    'clock_out' => '18:00:00',
                ]);

                AttendanceBreak::create([
                    'attendance_record_id' => $attendance->id,
                    'break_in' => '12:00:00',
                    'break_out' => '13:00:00',
                ]);

                $count++;
            }

            $date->addDay();
        }

        // --------------------------------
        // user3：2026年9月 17日分
        // --------------------------------

        $date = Carbon::create(2026, 9, 1);
        $count = 0;

        while ($count < 17) {

            if ($date->isWeekday()) {

                $attendance = AttendanceRecord::create([
                    'user_id' => $user3->id,
                    'date' => $date->format('Y-m-d'),
                    'clock_in' => '09:00:00',
                    'clock_out' => '18:00:00',
                ]);

                AttendanceBreak::create([
                    'attendance_record_id' => $attendance->id,
                    'break_in' => '12:00:00',
                    'break_out' => '13:00:00',
                ]);

                $count++;
            }

            $date->addDay();
        }
    }
}