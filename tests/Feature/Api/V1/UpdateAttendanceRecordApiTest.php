<?php

namespace Tests\Feature\Api\V1;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdateAttendanceRecordApiTest extends TestCase
{
    // テストごとにデータベースを初期化する
    use RefreshDatabase;

    /**
     * 認証済みユーザーが自分の勤怠を更新できる。
     *
     * @return void
     */
    public function test_authenticated_user_can_update_own_attendance_record(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // ユーザーに紐づく勤怠を作成する
        $attendanceRecord = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-09',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // Sanctumでユーザーを認証する
        Sanctum::actingAs($user);

        // 自分の勤怠に更新リクエストを送信する
        $response = $this->putJson(
            "/api/v1/attendance-records/{$attendanceRecord->id}",
            [
                'date' => '2026-11-09',
                'clock_in' => '10:00:00',
                'clock_out' => '19:00:00',
                'comment' => '勤務時間を修正',
            ]
        );

        // HTTPステータス200を確認する
        $response->assertStatus(200);

        // 更新されたJSONデータを確認する
        $response->assertJsonPath('data.clock_in', '10:00:00');

        // データベースの更新内容を確認する
        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendanceRecord->id,
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
            'comment' => '勤務時間を修正',
        ]);
    }

    /**
     * 存在しない勤怠IDを更新すると404を返す。
     *
     * @return void
     */
    public function test_nonexistent_attendance_record_returns_404(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // Sanctumでユーザーを認証する
        Sanctum::actingAs($user);

        // 存在しない勤怠IDに更新リクエストを送信する
        $response = $this->putJson('/api/v1/attendance-records/99999', [
            'date' => '2026-11-09',
            'clock_in' => '09:00:00',
        ]);

        // HTTPステータス404を確認する
        $response->assertStatus(404);

        // 仕様書どおりのエラーメッセージを確認する
        $response->assertJsonPath(
            'error',
            '勤怠情報が見つかりませんでした。'
        );
    }

    /**
     * 未認証ユーザーは勤怠を更新できない。
     *
     * @return void
     */
    public function test_unauthenticated_user_cannot_update_attendance_record(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // 勤怠を作成する
        $attendanceRecord = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-09',
            'clock_in' => '09:00:00',
        ]);

        // 認証せずに更新リクエストを送信する
        $response = $this->putJson(
            "/api/v1/attendance-records/{$attendanceRecord->id}",
            [
                'date' => '2026-11-09',
                'clock_in' => '10:00:00',
            ]
        );

        // HTTPステータス401を確認する
        $response->assertStatus(401);

        // 未認証エラーメッセージを確認する
        $response->assertJsonPath('message', 'Unauthenticated.');
    }

    /**
     * 一般ユーザーは他人の勤怠を更新できない。
     *
     * @return void
     */
    public function test_user_cannot_update_another_users_attendance_record(): void
    {
        // 勤怠の所有者を作成する
        $owner = User::factory()->create();

        // 別の一般ユーザーを作成する
        $otherUser = User::factory()->create([
            'admin_status' => false,
        ]);

        // 所有者の勤怠を作成する
        $attendanceRecord = AttendanceRecord::create([
            'user_id' => $owner->id,
            'date' => '2026-11-09',
            'clock_in' => '09:00:00',
        ]);

        // 別の一般ユーザーとして認証する
        Sanctum::actingAs($otherUser);

        // 他人の勤怠に更新リクエストを送信する
        $response = $this->putJson(
            "/api/v1/attendance-records/{$attendanceRecord->id}",
            [
                'date' => '2026-11-09',
                'clock_in' => '10:00:00',
            ]
        );

        // HTTPステータス403を確認する
        $response->assertStatus(403);

        // 仕様書どおりのエラーメッセージを確認する
        $response->assertJsonPath(
            'error',
            'この操作を実行する権限がありません。'
        );

        // 他人の勤怠が更新されていないことを確認する
        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendanceRecord->id,
            'clock_in' => '09:00:00',
        ]);
    }

    /**
     * 管理者は他ユーザーの勤怠を更新できる。
     *
     * @return void
     */
    public function test_admin_can_update_another_users_attendance_record(): void
    {
        // 勤怠の所有者を作成する
        $owner = User::factory()->create();

        // 管理者ユーザーを作成する
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        // 所有者の勤怠を作成する
        $attendanceRecord = AttendanceRecord::create([
            'user_id' => $owner->id,
            'date' => '2026-11-09',
            'clock_in' => '09:00:00',
        ]);

        // 管理者として認証する
        Sanctum::actingAs($admin);

        // 他ユーザーの勤怠に更新リクエストを送信する
        $response = $this->putJson(
            "/api/v1/attendance-records/{$attendanceRecord->id}",
            [
                'date' => '2026-11-09',
                'clock_in' => '10:00:00',
            ]
        );

        // HTTPステータス200を確認する
        $response->assertStatus(200);

        // 更新後のデータを確認する
        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendanceRecord->id,
            'clock_in' => '10:00:00',
        ]);
    }

    /**
     * 同じユーザーの別の勤怠日と重複した場合に422を返す。
     *
     * @return void
     */
    public function test_duplicate_attendance_date_returns_validation_error(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // 更新対象の勤怠を作成する
        $attendanceRecord = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-09',
            'clock_in' => '09:00:00',
        ]);

        // 同じユーザーの別の日付の勤怠を作成する
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-10',
            'clock_in' => '09:00:00',
        ]);

        // Sanctumで認証する
        Sanctum::actingAs($user);

        // 既に登録されている日付へ変更しようとする
        $response = $this->putJson(
            "/api/v1/attendance-records/{$attendanceRecord->id}",
            [
                'date' => '2026-11-10',
                'clock_in' => '10:00:00',
            ]
        );

        // HTTPステータス422を確認する
        $response->assertStatus(422);

        // 日付の重複エラーメッセージを確認する
        $response->assertJsonPath(
            'errors.date.0',
            'この日付の勤怠は既に登録されています。'
        );

        // 更新対象の日付が変更されていないことを確認する
        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendanceRecord->id,
            'date' => '2026-11-09',
        ]);
    }

    /**
     * 更新対象自身の日付は重複エラーにならない。
     *
     * @return void
     */
    public function test_own_attendance_date_is_not_treated_as_duplicate(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // 更新対象の勤怠を作成する
        $attendanceRecord = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-09',
            'clock_in' => '09:00:00',
        ]);

        // Sanctumで認証する
        Sanctum::actingAs($user);

        // 日付は変えずに出勤時刻だけ変更する
        $response = $this->putJson(
            "/api/v1/attendance-records/{$attendanceRecord->id}",
            [
                'date' => '2026-11-09',
                'clock_in' => '10:00:00',
            ]
        );

        // HTTPステータス200を確認する
        $response->assertStatus(200);

        // 出勤時刻が更新されたことを確認する
        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendanceRecord->id,
            'date' => '2026-11-09',
            'clock_in' => '10:00:00',
        ]);
    }

    /**
     * PATCHリクエストでも勤怠を更新できる。
     *
     * @return void
     */
    public function test_authenticated_user_can_update_attendance_with_patch(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // 更新対象の勤怠を作成する
        $attendanceRecord = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-09',
            'clock_in' => '09:00:00',
        ]);

        // Sanctumで認証する
        Sanctum::actingAs($user);

        // PATCHで勤怠データを更新する
        $response = $this->patchJson(
            "/api/v1/attendance-records/{$attendanceRecord->id}",
            [
                'date' => '2026-11-09',
                'clock_in' => '10:00:00',
                'comment' => 'PATCHで修正',
            ]
        );

        // HTTPステータス200を確認する
        $response->assertStatus(200);

        // 更新後のJSONを確認する
        $response->assertJsonPath('data.clock_in', '10:00:00');

        // データベースの更新内容を確認する
        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendanceRecord->id,
            'clock_in' => '10:00:00',
            'comment' => 'PATCHで修正',
        ]);
    }
}
