<?php

namespace Tests\Feature\Api\V1;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceRecordApiTest extends TestCase
{
    // テストごとにデータベースを初期化する
    use RefreshDatabase;

    /**
     * AP01：勤怠一覧をJSON形式で取得できることを確認する。
     */
    public function test_can_get_attendance_records(): void
    {
        // テスト用のユーザーを作成する
        $user = User::factory()->create();

        // テスト用の勤怠データを作成する
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-09-23',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => null,
        ]);

        // 勤怠一覧APIにGETリクエストを送信する
        $response = $this->getJson('/api/v1/attendance-records');

        // HTTPステータスが200であることを確認する
        // 200 (OK) : クライアントからのリクエストがサーバーによって正常に処理され、成功したことを示す標準的なレスポンスコード
        $response->assertOk();

        // JSONに作成した勤怠データが含まれることを確認する
        $response->assertJsonFragment([
            'id' => $attendance->id,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'date' => '2026-09-23',
        ]);

        // ページネーション情報が存在することを確認する
        $response->assertJsonStructure([
            'data',
            'links',
            'meta',
        ]);
    }

    /**
     * AP01：ユーザーIDで勤怠を絞り込めることを確認する。
     */
    public function test_can_filter_attendance_records_by_user_id(): void
    {
        // 2人のテスト用ユーザーを作成する
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // ユーザー1の勤怠を作成する
        AttendanceRecord::create([
            'user_id' => $user1->id,
            'date' => '2026-09-23',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // ユーザー2の勤怠を作成する
        AttendanceRecord::create([
            'user_id' => $user2->id,
            'date' => '2026-09-23',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // ユーザー1の勤怠だけを取得する
        $response = $this->getJson(
            '/api/v1/attendance-records?user_id=' . $user1->id
        );

        // HTTPステータスが200であることを確認する
        $response->assertOk();

        // 取得件数が1件であることを確認する
        $response->assertJsonCount(1, 'data');

        // ユーザー1の勤怠が返っていることを確認する
        $response->assertJsonPath('data.0.user_id', $user1->id);
    }

    /**
     * AP02：指定した勤怠の詳細を取得できることを確認する。
     */
    public function test_can_get_attendance_record_detail(): void
    {
        // テスト用のユーザーを作成する
        $user = User::factory()->create();

        // テスト用の勤怠データを作成する
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-09-23',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 勤怠詳細APIにGETリクエストを送信する
        $response = $this->getJson(
            '/api/v1/attendance-records/' . $attendance->id
        );

        // HTTPステータスが200であることを確認する
        $response->assertOk();

        // 指定した勤怠IDが返っていることを確認する
        $response->assertJsonPath('data.id', $attendance->id);

        // ユーザー名が返っていることを確認する
        $response->assertJsonPath('data.user.name', $user->name);

        // 休憩と修正申請の配列が存在することを確認する
        $response->assertJsonStructure([
            'data' => [
                'breaks',
                'applications',
            ],
        ]);
    }

    /**
     * AP02：存在しない勤怠IDでは404エラーを返すことを確認する。
     */
    public function test_returns_404_when_attendance_record_does_not_exist(): void
    {
        // 存在しない勤怠IDを指定してGETリクエストを送信する
        $response = $this->getJson('/api/v1/attendance-records/999999');

        // HTTPステータスが404であることを確認する
        $response->assertNotFound();

        // 仕様書どおりの日本語エラーメッセージを確認する
        $response->assertExactJson([
            'error' => '勤怠情報が見つかりませんでした。',
        ]);
    }
}
