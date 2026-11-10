<?php

namespace Tests\Feature\Api\V1;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeleteAttendanceRecordApiTest extends TestCase
{
    // テストごとにデータベースを初期化する
    use RefreshDatabase;

    /**
     * 本人が自分の勤怠を削除できることを確認する。
     */
    public function test_authenticated_user_can_delete_own_attendance_record(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // ユーザーの勤怠を作成する
        $attendanceRecord = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-09',
            'clock_in' => '09:00:00',
        ]);

        // Sanctumで本人として認証する
        Sanctum::actingAs($user);

        // DELETEリクエストを送信する
        $response = $this->deleteJson(
            "/api/v1/attendance-records/{$attendanceRecord->id}"
        );

        // HTTPステータス204を確認する
        $response->assertNoContent();

        // データベースから勤怠が削除されたことを確認する
        $this->assertDatabaseMissing('attendance_records', [
            'id' => $attendanceRecord->id,
        ]);
    }

    /**
     * 存在しない勤怠IDの場合は404を返す。
     */
    public function test_nonexistent_attendance_record_returns_404(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // Sanctumで認証する
        Sanctum::actingAs($user);

        // 存在しないIDを指定して削除を試みる
        $response = $this->deleteJson(
            '/api/v1/attendance-records/99999'
        );

        // HTTPステータス404を確認する
        $response->assertStatus(404);

        // 仕様書どおりのエラーメッセージを確認する
        $response->assertJsonPath(
            'error',
            '勤怠情報が見つかりませんでした。'
        );
    }

    /**
     * 未認証ユーザーは勤怠を削除できない。
     */
    public function test_unauthenticated_user_cannot_delete_attendance_record(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // 削除対象の勤怠を作成する
        $attendanceRecord = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-09',
            'clock_in' => '09:00:00',
        ]);

        // 認証せずにDELETEリクエストを送信する
        $response = $this->deleteJson(
            "/api/v1/attendance-records/{$attendanceRecord->id}"
        );

        // HTTPステータス401を確認する
        $response->assertStatus(401);

        // 未認証エラーメッセージを確認する
        $response->assertJsonPath(
            'message',
            'Unauthenticated.'
        );

        // 勤怠が削除されていないことを確認する
        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendanceRecord->id,
        ]);
    }

    /**
     * 一般ユーザーは他人の勤怠を削除できない。
     */
    public function test_user_cannot_delete_another_users_attendance_record(): void
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

        // 他人の勤怠の削除を試みる
        $response = $this->deleteJson(
            "/api/v1/attendance-records/{$attendanceRecord->id}"
        );

        // HTTPステータス403を確認する
        $response->assertStatus(403);

        // 仕様書どおりのエラーメッセージを確認する
        $response->assertJsonPath(
            'error',
            'この操作を実行する権限がありません。'
        );

        // 勤怠が削除されていないことを確認する
        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendanceRecord->id,
        ]);
    }

    /**
     * 管理者は他人の勤怠を削除できる。
     */
    public function test_admin_can_delete_another_users_attendance_record(): void
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

        // 他人の勤怠を削除する
        $response = $this->deleteJson(
            "/api/v1/attendance-records/{$attendanceRecord->id}"
        );

        // HTTPステータス204を確認する
        $response->assertNoContent();

        // 勤怠が削除されたことを確認する
        $this->assertDatabaseMissing('attendance_records', [
            'id' => $attendanceRecord->id,
        ]);
    }

    /**
     * 勤怠削除時に関連する休憩データも削除される。
     */
    public function test_deleting_attendance_record_cascades_to_breaks(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // 勤怠データを作成する
        $attendanceRecord = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-09',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 勤怠に紐づく休憩データを作成する
        $attendanceBreak = $attendanceRecord->breaks()->create([
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        // Sanctumで本人として認証する
        Sanctum::actingAs($user);

        // 勤怠を削除する
        $response = $this->deleteJson(
            "/api/v1/attendance-records/{$attendanceRecord->id}"
        );

        // HTTPステータス204を確認する
        $response->assertNoContent();

        // 親の勤怠が削除されたことを確認する
        $this->assertDatabaseMissing('attendance_records', [
            'id' => $attendanceRecord->id,
        ]);

        // 関連する休憩も削除されたことを確認する
        $this->assertDatabaseMissing('breaks', [
            'id' => $attendanceBreak->id,
        ]);
    }
}
