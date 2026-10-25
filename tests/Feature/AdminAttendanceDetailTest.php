<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AttendanceRecord;
use App\Models\AttendanceBreak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceDetailTest extends TestCase
{
    // テストごとにデータベースを初期化する
    use RefreshDatabase;

    /**
     * 管理者の勤怠詳細画面に正しい勤怠情報が表示される
     */
    public function test_admin_attendance_detail_displays_correct_information(): void
    {
        // 管理者ユーザーを作成する
        $admin = User::factory()->create([
            'name' => '管理者',
            'admin_status' => true,
        ]);

        // 一般ユーザーを作成する
        $user = User::factory()->create([
            'name' => 'テストユーザー',
            'admin_status' => false,
        ]);

        // テスト対象の勤怠データを作成する
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-11',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 勤怠に紐づく休憩データを作成する
        AttendanceBreak::create([
            'attendance_record_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        // 管理者としてログインして勤怠詳細画面にアクセスする
        $response = $this->actingAs($admin, 'admin')
            ->get('/admin/attendance/' . $attendance->id);

        // 画面が正常に表示されることを確認する
        $response->assertStatus(200);

        // 対象スタッフの名前が表示されることを確認する
        $response->assertSee('テストユーザー');

        // 勤怠の日付（年）が表示されることを確認する
        $response->assertSee('2026年');

        // 勤怠の日付（月日）が表示されることを確認する
        $response->assertSee('10月11日');

        // 出勤時刻が表示されることを確認する
        $response->assertSee('09:00');

        // 退勤時刻が表示されることを確認する
        $response->assertSee('18:00');

        // 休憩開始時刻が表示されることを確認する
        $response->assertSee('12:00');

        // 休憩終了時刻が表示されることを確認する
        $response->assertSee('13:00');
    }

    /**
     * 出勤時刻が退勤時刻より後の場合、バリデーションエラーになる
     */
    public function test_admin_cannot_update_attendance_with_invalid_clock_in(): void
    {
        // 管理者ユーザーを作成する
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        // 一般ユーザーを作成する
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        // テスト用の勤怠データを作成する
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-11',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 管理者としてログインする
        $this->actingAs($admin, 'admin');

        // 出勤時刻が退勤時刻より後になるデータを送信する
        $response = $this->from('/admin/attendance/' . $attendance->id)
            ->post('/attendance/' . $attendance->id, [
                'new_clock_in' => '19:00',
                'new_clock_out' => '18:00',
                'new_break_in' => [],
                'new_break_out' => [],
                'comment' => '勤怠修正',
            ]);

        // バリデーションエラーで元の画面に戻ることを確認する
        $response->assertRedirect(
            '/admin/attendance/' . $attendance->id
        );

        // 出勤・退勤時刻のバリデーションエラーを確認する
        $response->assertSessionHasErrors('new_clock_out');

        // 不正なデータで勤怠が更新されていないことを確認する
        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
    }

    /**
     * 管理者が正しい勤怠情報を送信した場合、更新される
     */
    public function test_admin_can_update_attendance(): void
    {
        // 管理者を作成する
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        // 一般ユーザーを作成する
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        // 修正対象の勤怠データを作成する
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-11',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 管理者としてログインする
        $this->actingAs($admin, 'admin');

        // 正しい勤怠情報を送信する
        $response = $this->post('/attendance/' . $attendance->id, [
            'new_clock_in' => '09:30',
            'new_clock_out' => '18:30',
            'new_break_in' => ['12:00'],
            'new_break_out' => ['13:00'],
            'comment' => '勤怠時間の修正',
        ]);

        // 管理者の勤怠詳細画面に戻ることを確認する
        $response->assertRedirect(
            '/admin/attendance/' . $attendance->id
        );

        // 出勤・退勤時刻が更新されたことを確認する
        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'clock_in' => '09:30:00',
            'clock_out' => '18:30:00',
        ]);

        // 休憩時間が保存されたことを確認する
        $this->assertDatabaseHas('breaks', [
            'attendance_record_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);
    }
}