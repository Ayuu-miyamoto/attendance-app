<?php

namespace Tests\Feature;

use App\Models\AttendanceBreak;
use App\Models\AttendanceCorrect;
use App\Models\AttendanceRecord;
use App\Models\BreakCorrect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    // テスト用の管理者・一般ユーザー・勤怠を作成する
    private function createAttendance(): array
    {
        // 管理者を作成する
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        // 一般ユーザーを作成する
        $user = User::factory()->create([
            'name' => 'テストスタッフ',
            'admin_status' => false,
        ]);

        // 一般ユーザーの勤怠を作成する
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-10',
            'clock_in' => '09:00',
            'clock_out' => '18:00',
        ]);

        // 作成したデータを返す
        return [$admin, $user, $attendance];
    }

    // 指定した勤怠に対する修正申請を作成する
    private function createCorrection(
        AttendanceRecord $attendance,
        string $comment,
        bool $approved = false
    ): AttendanceCorrect {

        // 修正申請を作成する
        return AttendanceCorrect::create([
            'attendance_record_id' => $attendance->id,
            'clock_in' => '10:00',
            'clock_out' => '19:00',
            'comment' => $comment,
            'approval_status' => $approved,
            'requested_at' => now(),
        ]);
    }

    // 承認待ちの修正申請がすべて表示される
    public function test_admin_can_see_all_pending_corrections(): void
    {
        // 管理者と勤怠データを作成する
        [$admin, $user, $attendance] = $this->createAttendance();

        // 承認待ちの申請を2件作成する
        $pending1 = $this->createCorrection(
            $attendance,
            '承認待ち申請その1'
        );

        $pending2 = $this->createCorrection(
            $attendance,
            '承認待ち申請その2'
        );

        // 承認済みの申請も作成する
        $approved = $this->createCorrection(
            $attendance,
            '承認済み申請',
            true
        );

        // 管理者としてログインする
        $this->actingAs($admin, 'admin');

        // 修正申請一覧画面を開く
        $response = $this->get('/stamp_correction_request/list');

        // 正常に表示されることを確認する
        $response->assertOk();

        // 承認待ちタブに申請が存在することを確認する
        $response->assertSee('承認待ち申請その1');
        $response->assertSee('承認待ち申請その2');

        // 承認待ち申請の詳細リンクを確認する
        $response->assertSee(
            '/stamp_correction_request/approve/' . $pending1->id,
            false
        );

        $response->assertSee(
            '/stamp_correction_request/approve/' . $pending2->id,
            false
        );

        // Bladeの承認待ち表示領域を確認する
        $this->assertStringContainsString(
            '承認待ち申請その1',
            $response->getContent()
        );

        // 承認済みの申請も一覧に含まれていることを確認する
        $response->assertSee('承認済み申請');
    }

    // 承認済みの修正申請がすべて表示される
    public function test_admin_can_see_all_approved_corrections(): void
    {
        // 管理者と勤怠データを作成する
        [$admin, $user, $attendance] = $this->createAttendance();

        // 承認済みの申請を2件作成する
        $approved1 = $this->createCorrection(
            $attendance,
            '承認済み申請その1',
            true
        );

        $approved2 = $this->createCorrection(
            $attendance,
            '承認済み申請その2',
            true
        );

        // 管理者としてログインする
        $this->actingAs($admin, 'admin');

        // 修正申請一覧画面を開く
        $response = $this->get('/stamp_correction_request/list');

        // 正常に表示されることを確認する
        $response->assertOk();

        // 承認済みの申請理由を確認する
        $response->assertSee('承認済み申請その1');
        $response->assertSee('承認済み申請その2');

        // 各申請の詳細リンクを確認する
        $response->assertSee(
            '/stamp_correction_request/approve/' . $approved1->id,
            false
        );

        $response->assertSee(
            '/stamp_correction_request/approve/' . $approved2->id,
            false
        );

        // 承認済みタブの見出しが存在することを確認する
        $response->assertSee('承認済み');
    }

    // 修正申請の詳細情報が正しく表示される
    public function test_admin_can_see_correction_detail(): void
    {
        // 管理者と勤怠データを作成する
        [$admin, $user, $attendance] = $this->createAttendance();

        // 修正申請を作成する
        $application = $this->createCorrection(
            $attendance,
            '電車遅延のため修正'
        );

        // 修正申請の休憩時間を作成する
        BreakCorrect::create([
            'attendance_correct_id' => $application->id,
            'break_in' => '12:30',
            'break_out' => '13:30',
        ]);

        // 管理者としてログインする
        $this->actingAs($admin, 'admin');

        // 修正申請詳細画面を開く
        $response = $this->get(
            '/stamp_correction_request/approve/' . $application->id
        );

        // 正常に表示されることを確認する
        $response->assertOk();

        // 申請者の名前を確認する
        $response->assertSee('テストスタッフ');

        // 対象日付を確認する
        $response->assertSee('2026年');
        $response->assertSee('11月10日');

        // 修正後の出勤・退勤時刻を確認する
        $response->assertSee('10:00');
        $response->assertSee('19:00');

        // 修正後の休憩時間を確認する
        $response->assertSee('12:30');
        $response->assertSee('13:30');

        // 申請理由を確認する
        $response->assertSee('電車遅延のため修正');

        // 承認ボタンが表示されることを確認する
        $response->assertSee('>承認</button>', false);
    }

    // 承認すると勤怠情報と承認状態が更新される
    public function test_admin_can_approve_correction(): void
    {
        // 管理者と勤怠データを作成する
        [$admin, $user, $attendance] = $this->createAttendance();

        // 修正申請を作成する
        $application = $this->createCorrection(
            $attendance,
            '勤務時間の修正'
        );

        // 修正後の休憩時間を作成する
        BreakCorrect::create([
            'attendance_correct_id' => $application->id,
            'break_in' => '13:00',
            'break_out' => '14:00',
        ]);

        // 管理者としてログインする
        $this->actingAs($admin, 'admin');

        // 修正申請を承認する
        $response = $this->post(
            '/stamp_correction_request/approve/' . $application->id
        );

        // 申請詳細画面へ戻ることを確認する
        $response->assertRedirect(
            '/stamp_correction_request/approve/' . $application->id
        );

        // 修正申請が承認済みになったことを確認する
        $this->assertDatabaseHas('attendance_corrects', [
            'id' => $application->id,
            'approval_status' => true,
        ]);

        // 承認日時が保存されたことを確認する
        $this->assertNotNull(
            $application->fresh()->approved_at
        );

        // 勤怠の出勤・退勤時刻が更新されたことを確認する
        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
        ]);

        // 休憩時間が更新されたことを確認する
        $this->assertDatabaseHas('breaks', [
            'attendance_record_id' => $attendance->id,
            'break_in' => '13:00:00',
            'break_out' => '14:00:00',
        ]);
    }
}
