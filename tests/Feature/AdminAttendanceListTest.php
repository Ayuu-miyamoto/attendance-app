<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceListTest extends TestCase
{
    // テストごとにデータベースを初期化する
    use RefreshDatabase;

    /**
     * ① 管理者の勤怠一覧に当日の全ユーザーの勤怠情報が表示される
     */
    public function test_admin_can_see_all_users_attendance(): void
    {
        // 管理者ユーザーを作成する
        $admin = User::factory()->create([
            'name' => '管理者',
            'admin_status' => true,
        ]);

        // 一般ユーザー1を作成する
        $user1 = User::factory()->create([
            'name' => 'ユーザー1',
            'admin_status' => false,
        ]);

        // 一般ユーザー2を作成する
        $user2 = User::factory()->create([
            'name' => 'ユーザー2',
            'admin_status' => false,
        ]);

        // テスト上の現在日付を固定する
        Carbon::setTestNow('2026-10-25 10:00:00');

        // ユーザー1の当日の勤怠を作成する
        AttendanceRecord::create([
            'user_id' => $user1->id,
            'date' => '2026-10-25',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // ユーザー2の当日の勤怠を作成する
        AttendanceRecord::create([
            'user_id' => $user2->id,
            'date' => '2026-10-25',
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
        ]);

        // 管理者としてログインして勤怠一覧を表示する
        $response = $this->actingAs($admin, 'admin')
            ->get('/admin/attendance/list');

        // 画面が正常に表示されることを確認する
        $response->assertStatus(200);

        // ユーザー1の名前が表示されることを確認する
        $response->assertSee('ユーザー1');

        // ユーザー2の名前が表示されることを確認する
        $response->assertSee('ユーザー2');

        // ユーザー1の出勤時刻が表示されることを確認する
        $response->assertSee('09:00');

        // ユーザー2の出勤時刻が表示されることを確認する
        $response->assertSee('10:00');

        // テスト上の現在日付を解除する
        Carbon::setTestNow();
    }

    /**
     * ② 管理者の勤怠一覧に現在の日付が表示される
     */
    public function test_admin_attendance_list_displays_current_date(): void
    {
        // 管理者ユーザーを作成する
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        // テスト上の現在日付を固定する
        Carbon::setTestNow('2026-10-25 10:00:00');

        // 管理者としてログインして勤怠一覧を表示する
        $response = $this->actingAs($admin, 'admin')
            ->get('/admin/attendance/list');

        // 画面が正常に表示されることを確認する
        $response->assertStatus(200);

        // 現在の日付が表示されることを確認する
        $response->assertSee('2026年10月25日');

        // テスト上の現在日付を解除する
        Carbon::setTestNow();
    }

    /**
     * ③ 前日の日付を指定すると前日の勤怠情報が表示される
     */
    public function test_admin_can_see_previous_day_attendance(): void
    {
        // 管理者ユーザーを作成する
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        // 一般ユーザーを作成する
        $user = User::factory()->create([
            'name' => '前日勤務ユーザー',
            'admin_status' => false,
        ]);

        // 前日の勤怠データを作成する
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-24',
            'clock_in' => '08:30:00',
            'clock_out' => '17:30:00',
        ]);

        // 管理者としてログインし、前日の日付を指定する
        $response = $this->actingAs($admin, 'admin')
            ->get('/admin/attendance/list?date=2026-10-24');

        // 画面が正常に表示されることを確認する
        $response->assertStatus(200);

        // 前日勤務ユーザーの名前が表示されることを確認する
        $response->assertSee('前日勤務ユーザー');

        // 前日の出勤時刻が表示されることを確認する
        $response->assertSee('08:30');

        // 前日の退勤時刻が表示されることを確認する
        $response->assertSee('17:30');
    }

    /**
     * ④ 翌日の日付を指定すると翌日の勤怠情報が表示される
     */
    public function test_admin_can_see_next_day_attendance(): void
    {
        // 管理者ユーザーを作成する
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        // 一般ユーザーを作成する
        $user = User::factory()->create([
            'name' => '翌日勤務ユーザー',
            'admin_status' => false,
        ]);

        // 翌日の勤怠データを作成する
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-26',
            'clock_in' => '09:15:00',
            'clock_out' => '18:15:00',
        ]);

        // 管理者としてログインし、翌日の日付を指定する
        $response = $this->actingAs($admin, 'admin')
            ->get('/admin/attendance/list?date=2026-10-26');

        // 画面が正常に表示されることを確認する
        $response->assertStatus(200);

        // 翌日勤務ユーザーの名前が表示されることを確認する
        $response->assertSee('翌日勤務ユーザー');

        // 翌日の出勤時刻が表示されることを確認する
        $response->assertSee('09:15');

        // 翌日の退勤時刻が表示されることを確認する
        $response->assertSee('18:15');
    }
}