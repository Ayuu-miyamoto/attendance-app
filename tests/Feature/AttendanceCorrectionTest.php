<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AttendanceRecord;
use App\Models\AttendanceCorrect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceCorrectionTest extends TestCase
{
    // テストごとにデータベースを初期化する
    use RefreshDatabase;

    /**
     * ① 出勤時刻が退勤時刻より後の場合、バリデーションエラーになる
     */
    public function test_clock_in_after_clock_out_is_invalid(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // 修正対象の勤怠データを作成する
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-11',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 出勤時刻を退勤時刻より後にして修正申請する
        $response = $this->actingAs($user)->post(
            '/attendance/' . $attendance->id,
            [
                'new_clock_in' => '19:00',
                'new_clock_out' => '18:00',
                'comment' => '出勤時刻の修正',
            ]
        );

        // 出勤・退勤時刻のバリデーションエラーを確認する
        $response->assertSessionHasErrors([
            'new_clock_out' => '出勤時間もしくは退勤時間が不適切な値です。',
        ]);

        // 不正な修正申請が保存されていないことを確認する
        $this->assertDatabaseCount('attendance_corrects', 0);
    }

    /**
     * ② 休憩終了時刻が退勤時刻より後の場合、バリデーションエラーになる
     */
    public function test_break_out_after_clock_out_is_invalid(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // 修正対象の勤怠データを作成する
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-11',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 休憩終了時刻を退勤時刻より後にして修正申請する
        $response = $this->actingAs($user)->post(
            '/attendance/' . $attendance->id,
            [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
                'new_break_in' => ['17:00'],
                'new_break_out' => ['19:00'],
                'comment' => '休憩時間の修正',
            ]
        );

        // 休憩終了時刻のバリデーションエラーを確認する
        $response->assertSessionHasErrors([
            'new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です。',
        ]);

        // 不正な修正申請が保存されていないことを確認する
        $this->assertDatabaseCount('attendance_corrects', 0);
    }

    /**
     * ③ 備考が未入力の場合、バリデーションエラーになる
     */
    public function test_comment_is_required(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // 修正対象の勤怠データを作成する
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-11',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 備考を空欄にして修正申請する
        $response = $this->actingAs($user)->post(
            '/attendance/' . $attendance->id,
            [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
                'comment' => '',
            ]
        );

        // 備考のバリデーションエラーを確認する
        $response->assertSessionHasErrors([
            'comment' => '備考を記入してください。',
        ]);

        // 不正な修正申請が保存されていないことを確認する
        $this->assertDatabaseCount('attendance_corrects', 0);
    }

    /**
     * ④ 正しい内容で修正申請するとDBに保存される
     */
    public function test_attendance_correction_request_is_saved(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // 修正対象の勤怠データを作成する
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-11',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // ログインして正しい内容で修正申請する
        $response = $this->actingAs($user)->post(
            '/attendance/' . $attendance->id,
            [
                'new_clock_in' => '09:30',
                'new_clock_out' => '18:30',
                'new_break_in' => ['12:00'],
                'new_break_out' => ['13:00'],
                'comment' => '出勤時間の修正',
            ]
        );

        // 修正申請後に勤怠詳細画面へ戻ることを確認する
        $response->assertRedirect('/attendance/' . $attendance->id);

        // 修正申請が承認待ちで保存されていることを確認する
        $this->assertDatabaseHas('attendance_corrects', [
            'attendance_record_id' => $attendance->id,
            'clock_in' => '09:30',
            'clock_out' => '18:30',
            'comment' => '出勤時間の修正',
            'approval_status' => false,
        ]);

        // 修正申請が1件だけ保存されていることを確認する
        $this->assertDatabaseCount('attendance_corrects', 1);

        // 今回保存された修正申請を取得する
        $correction = AttendanceCorrect::where(
            'attendance_record_id',
            $attendance->id
        )->firstOrFail();

        // 修正申請に紐づく休憩時間が保存されていることを確認する
        $this->assertDatabaseHas('break_correct_applications', [
            'attendance_correct_id' => $correction->id,
            'break_in' => '12:00',
            'break_out' => '13:00',
        ]);

        // 休憩修正データが1件だけ保存されていることを確認する
        $this->assertDatabaseCount('break_correct_applications', 1);
    }

    /**
     * ⑤ 承認待ちの修正申請が申請一覧に表示される
     */
    public function test_pending_correction_is_displayed_in_application_list(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create([
            'name' => 'テストユーザー',
        ]);

        // 修正対象の勤怠データを作成する
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-11',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 承認待ちの修正申請を作成する
        $correction = AttendanceCorrect::create([
            'attendance_record_id' => $attendance->id,
            'clock_in' => '09:30',
            'clock_out' => '18:30',
            'comment' => '出勤時間の修正',
            'approval_status' => false,
        ]);

        // 申請日時を設定する
        $correction->requested_at = '2026-10-12 10:00:00';

        // 申請日時をDBに保存する
        $correction->save();

        // ログインして申請一覧画面を表示する
        $response = $this->actingAs($user)
            ->get('/stamp_correction_request/list');

        // 画面が正常に表示されることを確認する
        $response->assertStatus(200);

        // 承認待ちの申請が表示されることを確認する
        $response->assertSee('承認待ち');

        // 申請者の名前が表示されることを確認する
        $response->assertSee('テストユーザー');

        // 修正対象の日付が表示されることを確認する
        $response->assertSee('2026/10/11');

        // 申請理由が表示されることを確認する
        $response->assertSee('出勤時間の修正');
    }

    /**
     * ⑥ 承認済みの修正申請が申請一覧に表示される
     */
    public function test_approved_correction_is_displayed_in_application_list(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // 修正対象の勤怠データを作成する
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-11',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 承認済みの修正申請を作成する
        $correction = AttendanceCorrect::create([
            'attendance_record_id' => $attendance->id,
            'clock_in' => '09:30',
            'clock_out' => '18:30',
            'comment' => '退勤時間の修正',
            'approval_status' => true,
        ]);

        // 申請日時を設定する
        $correction->requested_at = '2026-10-12 10:00:00';

        // 申請日時をDBに保存する
        $correction->save();

        // ログインして申請一覧画面を表示する
        $response = $this->actingAs($user)
            ->get('/stamp_correction_request/list');

        // 画面が正常に表示されることを確認する
        $response->assertStatus(200);

        // 承認済みの申請が表示されることを確認する
        $response->assertSee('承認済み');

        // 修正対象の日付が表示されることを確認する
        $response->assertSee('2026/10/11');

        // 申請理由が表示されることを確認する
        $response->assertSee('退勤時間の修正');
    }

    /**
     * ⑦ 申請一覧の詳細リンクから勤怠詳細画面に遷移できる
     */
    public function test_correction_detail_page_can_be_accessed(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // 修正対象の勤怠データを作成する
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-11',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 承認待ちの修正申請を作成する
        $correction = AttendanceCorrect::create([
            'attendance_record_id' => $attendance->id,
            'clock_in' => '09:30',
            'clock_out' => '18:30',
            'comment' => '出勤時間の修正',
            'approval_status' => false,
        ]);

        // ログインして申請一覧画面を表示する
        $response = $this->actingAs($user)
            ->get('/stamp_correction_request/list');

        // 申請詳細へのリンクが表示されることを確認する
        $response->assertSee(
            'href="' . url('/application/' . $correction->id) . '"',
            false
        );

        // 申請詳細リンクにアクセスする
        $detailResponse = $this->get('/application/' . $correction->id);

        // 勤怠詳細画面にリダイレクトされることを確認する
        $detailResponse->assertRedirect('/attendance/' . $attendance->id);

        // リダイレクト先の勤怠詳細画面にアクセスする
        $attendanceResponse = $this->get('/attendance/' . $attendance->id);

        // 勤怠詳細画面が正常に表示されることを確認する
        $attendanceResponse->assertStatus(200);
    }

    /**
     * ⑧ 承認待ちの修正申請がある場合、詳細画面から修正できない
     */
    public function test_pending_correction_disables_editing(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // 修正対象の勤怠データを作成する
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-11',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 承認待ちの修正申請を作成する
        AttendanceCorrect::create([
            'attendance_record_id' => $attendance->id,
            'clock_in' => '09:30',
            'clock_out' => '18:30',
            'comment' => '出勤時間の修正',
            'approval_status' => false,
        ]);

        // ログインして勤怠詳細画面を表示する
        $response = $this->actingAs($user)
            ->get('/attendance/' . $attendance->id);

        // 画面が正常に表示されることを確認する
        $response->assertStatus(200);

        // 承認待ちのメッセージが表示されることを確認する
        $response->assertSee('承認待ちのため修正できません');

        // 修正ボタンが表示されないことを確認する
        $response->assertDontSee('class="form__button--submit"', false);
    }

    /**
     * ⑨ 承認待ちの修正申請がある場合、重複申請できない
     */
    public function test_pending_correction_cannot_be_submitted_twice(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // 修正対象の勤怠データを作成する
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-11',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // すでに承認待ちの修正申請がある状態を作成する
        AttendanceCorrect::create([
            'attendance_record_id' => $attendance->id,
            'clock_in' => '09:30',
            'clock_out' => '18:30',
            'comment' => '最初の修正申請',
            'approval_status' => false,
        ]);

        // 同じ勤怠に対して、もう一度修正申請する
        $response = $this->actingAs($user)->post(
            '/attendance/' . $attendance->id,
            [
                'new_clock_in' => '10:00',
                'new_clock_out' => '19:00',
                'comment' => '2回目の修正申請',
            ]
        );

        // 勤怠詳細画面に戻ることを確認する
        $response->assertRedirect('/attendance/' . $attendance->id);

        // 修正申請が増えていないことを確認する
        $this->assertDatabaseCount('attendance_corrects', 1);

        // 2回目の修正申請が保存されていないことを確認する
        $this->assertDatabaseMissing('attendance_corrects', [
            'attendance_record_id' => $attendance->id,
            'comment' => '2回目の修正申請',
        ]);
    }
}