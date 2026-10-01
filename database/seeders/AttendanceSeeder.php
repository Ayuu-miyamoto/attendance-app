<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;

class AttendanceSeeder extends Seeder
{
    public function run(): void
{
    // ユーザー1を取得
    $user1 = User::where('email', 'user1@example.com')->first();

    for ($month = 5; $month >= 1; $month--) {
        $date = Carbon::today()->subMonths($month)->startOfMonth();
        $count = 0;

        while ($count < 15) {
            if ($date->isWeekday()) {
                Attendance::create([
                    'user_id' => $user1->id,
                    'date' => $date->toDateString(),
                    'start' => '09:00:00',
                    'finish' => '18:00:00',
                    'break_in' => '12:00:00',
                    'break_out' => '13:00:00',
                    'break_in_2' => null,
                    'break_out_2' => null,
                    'comment' => '',
                ]);

                $count++;
            }

            $date->addDay();
        }
    }
}
}
