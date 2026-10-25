<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AttendanceRecord;
use App\Models\AttendanceBreak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 勤怠詳細画面にログインユーザーの名前が表示される
     */
    public function test_attendance_detail_displays_user_name(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create([
            'name' => 'テストユーザー',
        ]);

        // テスト用の勤怠データを作成する
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-11',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // ログインして勤怠詳細画面を表示する
        $response = $this->actingAs($user)
            ->get('/attendance/' . $attendance->id);

        // 画面が正常に表示されることを確認する
        $response->assertStatus(200);

        // ログインユーザーの名前が表示されることを確認する
        $response->assertSee('value="テストユーザー"', false);
    }

    /**
     * 勤怠詳細画面に正しい日付が表示される
     */
    public function test_attendance_detail_displays_correct_date(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // 2026年10月11日の勤怠データを作成する
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-11',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // ログインして勤怠詳細画面を表示する
        $response = $this->actingAs($user)
            ->get('/attendance/' . $attendance->id);

        // 画面が正常に表示されることを確認する
        $response->assertStatus(200);

        // 年が正しく表示されることを確認する
        $response->assertSee('value="2026年"', false);

        // 月日が正しく表示されることを確認する
        $response->assertSee('value="10月11日"', false);
    }

    /**
     * 勤怠詳細画面に正しい出勤・退勤時刻が表示される
     */
    public function test_attendance_detail_displays_clock_in_and_out(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // 出勤9時、退勤18時の勤怠データを作成する
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-11',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // ログインして勤怠詳細画面を表示する
        $response = $this->actingAs($user)
            ->get('/attendance/' . $attendance->id);

        // 画面が正常に表示されることを確認する
        $response->assertStatus(200);

        // 出勤時刻が09:00と表示されることを確認する
        $response->assertSee('name="new_clock_in" value="09:00"', false);

        // 退勤時刻が18:00と表示されることを確認する
        $response->assertSee('name="new_clock_out" value="18:00"', false);
    }

    /**
     * 勤怠詳細画面に正しい休憩時刻が表示される
     */
    public function test_attendance_detail_displays_break_times(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // 勤怠データを作成する
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-11',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 休憩12時〜13時のデータを作成する
        AttendanceBreak::create([
            'attendance_record_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        // ログインして勤怠詳細画面を表示する
        $response = $this->actingAs($user)
            ->get('/attendance/' . $attendance->id);

        // 画面が正常に表示されることを確認する
        $response->assertStatus(200);

        // 休憩開始時刻が12:00と表示されることを確認する
        $response->assertSee('name="new_break_in[0]" value="12:00"', false);

        // 休憩終了時刻が13:00と表示されることを確認する
        $response->assertSee('name="new_break_out[0]" value="13:00"', false);
    }

}