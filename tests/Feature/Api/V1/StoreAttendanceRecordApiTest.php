<?php

namespace Tests\Feature\Api\V1;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreAttendanceRecordApiTest extends TestCase
{
    // テストごとにデータベースを初期化する
    use RefreshDatabase;

    /**
     * 認証済みユーザーが勤怠を登録できることを確認する。
     */
    public function test_authenticated_user_can_store_attendance_record(): void
    {
        // テスト用のユーザーを作成する
        $user = User::factory()->create();

        // Sanctumでユーザーを認証する
        Sanctum::actingAs($user);

        // 勤怠登録APIにJSONデータを送信する
        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-11-09',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '通常勤務',
        ]);

        // HTTPステータス201が返ることを確認する
        $response->assertStatus(201);

        // JSONに登録した勤怠情報が含まれることを確認する
        $response->assertJsonPath('data.date', '2026-11-09');
        $response->assertJsonPath('data.comment', '通常勤務');
        $response->assertJsonPath('data.user_id', $user->id);

        // データベースに勤怠情報が保存されていることを確認する
        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'date' => '2026-11-09',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '通常勤務',
        ]);
    }

    /**
     * 必須項目が未入力の場合に422が返ることを確認する。
     */
    public function test_required_fields_return_validation_errors(): void
    {
        // テスト用のユーザーを作成する
        $user = User::factory()->create();

        // Sanctumでユーザーを認証する
        Sanctum::actingAs($user);

        // 必須項目を指定せずに勤怠登録APIを呼び出す
        $response = $this->postJson('/api/v1/attendance-records', []);

        // HTTPステータス422が返ることを確認する
        $response->assertStatus(422);

        // 【追加】仕様書どおりmessageとerrorsのキーが存在することを確認する
        $response->assertJsonStructure([
            'message',
            'errors',
        ]);

        // 必須項目の日本語エラーメッセージを確認する
        $response->assertJsonPath('errors.date.0', '勤怠日は必須です。');
        $response->assertJsonPath('errors.clock_in.0', '出勤時刻は必須です。');
    }

    /**
     * 同じユーザーが同じ日付の勤怠を重複登録できないことを確認する。
     */
    public function test_duplicate_attendance_date_returns_validation_error(): void
    {
        // テスト用のユーザーを作成する
        $user = User::factory()->create();

        // 既存の勤怠データを登録する
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-09',
            'clock_in' => '09:00:00',
        ]);

        // Sanctumでユーザーを認証する
        Sanctum::actingAs($user);

        // 同じ日付の勤怠を登録しようとする
        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-11-09',
            'clock_in' => '10:00:00',
        ]);

        // HTTPステータス422が返ることを確認する
        $response->assertStatus(422);

        // 重複登録の日本語エラーメッセージを確認する
        $response->assertJsonPath(
            'errors.date.0',
            'この日付の勤怠は既に登録されています。'
        );

        // 同じユーザー・同じ日付の勤怠が1件だけであることを確認する
        $this->assertEquals(
            1,
            AttendanceRecord::where('user_id', $user->id)
                ->whereDate('date', '2026-11-09')
                ->count()
        );
    }

    /**
     * 未認証ユーザーが勤怠登録できないことを確認する。
     */
    public function test_unauthenticated_user_cannot_store_attendance_record(): void
    {
        // 認証せずに勤怠登録APIへJSONデータを送信する
        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-11-09',
            'clock_in' => '09:00:00',
        ]);

        // HTTPステータス401が返ることを確認する
        $response->assertStatus(401);

        // 未認証時のエラーメッセージを確認する
        $response->assertJsonPath('message', 'Unauthenticated.');

        // 勤怠データが登録されていないことを確認する
        $this->assertDatabaseCount('attendance_records', 0);
    }

    /**
     * 別のユーザーなら同じ日付の勤怠を登録できることを確認する。
     */
    public function test_different_user_can_store_same_attendance_date(): void
    {
        // 2人のテスト用ユーザーを作成する
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // 1人目の勤怠を登録する
        AttendanceRecord::create([
            'user_id' => $user1->id,
            'date' => '2026-11-09',
            'clock_in' => '09:00:00',
        ]);

        // 2人目のユーザーをSanctumで認証する
        Sanctum::actingAs($user2);

        // 2人目が同じ日付の勤怠を登録する
        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-11-09',
            'clock_in' => '10:00:00',
        ]);

        // HTTPステータス201が返ることを確認する
        $response->assertStatus(201);

        // 2人目のユーザーIDで登録されていることを確認する
        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user2->id,
            'date' => '2026-11-09',
        ]);

        // 2人分の勤怠が保存されていることを確認する
        $this->assertDatabaseCount('attendance_records', 2);
    }

    /**
     * 勤怠日がYYYY-MM-DD形式でない場合に422を返す。
     */
    public function test_invalid_date_format_returns_validation_error(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // Sanctumで認証する
        Sanctum::actingAs($user);

        // 不正な日付形式でAPIに送信する
        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026/11/09',
            'clock_in' => '09:00:00',
        ]);

        // バリデーションエラー422を確認する
        $response->assertStatus(422);

        // 仕様書どおりの日本語エラーメッセージを確認する
        $response->assertJsonPath(
            'errors.date.0',
            '勤怠日は YYYY-MM-DD 形式で指定してください。'
        );
    }

    /**
     * 出勤時刻がHH:MM:SS形式でない場合に422を返す。
     */
    public function test_invalid_clock_in_format_returns_validation_error(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // Sanctumで認証する
        Sanctum::actingAs($user);

        // 不正な出勤時刻でAPIに送信する
        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-11-09',
            'clock_in' => '09:00',
        ]);

        // バリデーションエラー422を確認する
        $response->assertStatus(422);

        // 出勤時刻の形式エラーを確認する
        $response->assertJsonPath(
            'errors.clock_in.0',
            '出勤時刻は HH:MM:SS 形式で指定してください。'
        );
    }

    /**
     * 退勤時刻が不正な場合に422を返す。
     */
    public function test_invalid_clock_out_returns_validation_error(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // Sanctumで認証する
        Sanctum::actingAs($user);

        // 出勤時刻より前の退勤時刻を送信する
        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-11-09',
            'clock_in' => '09:00:00',
            'clock_out' => '08:00:00',
        ]);

        // バリデーションエラー422を確認する
        $response->assertStatus(422);

        // 退勤時刻の前後関係のエラーを確認する
        $response->assertJsonPath(
            'errors.clock_out.0',
            '退勤時刻は出勤時刻より後の時刻を指定してください。'
        );
    }

    /**
     * 備考が255文字を超えた場合に422を返す。
     */
    public function test_comment_over_255_characters_returns_validation_error(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // Sanctumで認証する
        Sanctum::actingAs($user);

        // 256文字の備考を送信する
        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-11-09',
            'clock_in' => '09:00:00',
            'comment' => str_repeat('あ', 256),
        ]);

        // バリデーションエラー422を確認する
        $response->assertStatus(422);

        // 備考の文字数エラーを確認する
        $response->assertJsonPath(
            'errors.comment.0',
            '備考は 255 文字以内で入力してください。'
        );
    }

    /**
     * 退勤時刻がHH:MM:SS形式でない場合に422を返す。
     */
    public function test_invalid_clock_out_format_returns_validation_error(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // Sanctumでユーザーを認証する
        Sanctum::actingAs($user);

        // 退勤時刻を不正な形式で送信する
        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-11-09',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00',
        ]);

        // HTTPステータス422が返ることを確認する
        $response->assertStatus(422);

        // 仕様書どおりの日本語エラーメッセージを確認する
        $response->assertJsonPath(
            'errors.clock_out.0',
            '退勤時刻は HH:MM:SS 形式で指定してください。'
        );
    }
}
