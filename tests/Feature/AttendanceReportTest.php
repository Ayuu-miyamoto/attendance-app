<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceReportTest extends TestCase
{
    // テストごとにデータベースを初期化する
    use RefreshDatabase;

    // 各テストの前に実行する処理
    protected function setUp(): void
    {
        // 親クラスの初期化処理を実行する
        parent::setUp();

        // テスト中の現在日時を固定する
        Carbon::setTestNow('2026-11-13 12:00:00');
    }

    // 各テストの後に実行する処理
    protected function tearDown(): void
    {
        // 固定した現在日時を解除する
        Carbon::setTestNow();

        // 親クラスの終了処理を実行する
        parent::tearDown();
    }

    // ゲストはマイ勤怠レポートを閲覧できない
    public function test_guest_cannot_access_attendance_report(): void
    {
        // 未ログインでレポート画面にアクセスする
        $response = $this->get('/attendance/report');

        // ログイン画面へリダイレクトされることを確認する
        $response->assertRedirect('/login');
    }

    // 認証ユーザーの勤怠統計が正しく計算される
    public function test_authenticated_user_can_view_correct_attendance_statistics(): void
    {
        // メール認証済みの一般ユーザーを作成する
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // 別のユーザーを作成する
        $otherUser = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // 11月10日の勤怠を作成する（実労働8時間）
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-10',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ])->breaks()->create([
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        // 11月11日の勤怠を作成する（実労働11時間・残業3時間・遅刻）
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-11',
            'clock_in' => '09:30:00',
            'clock_out' => '21:30:00',
        ])->breaks()->create([
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        // 11月12日の勤怠を作成する（実労働7時間・早退）
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-12',
            'clock_in' => '09:00:00',
            'clock_out' => '17:00:00',
        ])->breaks()->create([
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        // 退勤していない勤怠を作成する（平均の分母に含めない）
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-13',
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        // 別ユーザーの勤怠を作成する（集計対象外）
        AttendanceRecord::create([
            'user_id' => $otherUser->id,
            'date' => '2026-11-10',
            'clock_in' => '09:00:00',
            'clock_out' => '20:00:00',
        ]);

        // 一般ユーザーとしてログインする
        $response = $this->actingAs($user, 'web')
            ->get('/attendance/report');

        // レポート画面が正常に表示されることを確認する
        $response->assertOk();

        // 正しいBladeが使用されていることを確認する
        $response->assertViewIs('reports.index');

        // 総労働26時間・残業3時間・平均8時間40分を確認する
        $response->assertViewHas('summary', [
            'total_work_minutes' => 1560,
            'total_overtime_minutes' => 180,
            'avg_work_minutes' => 520,
        ]);

        // 月次推移が6ヶ月分あることを確認する
        $response->assertViewHas('monthlyTrend', function ($trend) {
            return $trend->count() === 6
                && $trend->first()['month'] === '2026-06'
                && $trend->first()['work_minutes'] === 0
                && $trend->last()['month'] === '2026-11'
                && $trend->last()['work_minutes'] === 1560
                && $trend->last()['overtime_minutes'] === 180;
        });

        // 遅刻1回・早退1回・長時間労働1日を確認する
        $response->assertViewHas('anomalies', [
            'late_count' => 1,
            'early_leave_count' => 1,
            'long_work_count' => 1,
        ]);
    }

    // 勤怠記録がないユーザーでも安全にレポートを表示できる
    public function test_user_without_attendance_records_can_view_report(): void
    {
        // メール認証済みの一般ユーザーを作成する
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // 勤怠記録がないユーザーとしてログインする
        $response = $this->actingAs($user, 'web')
            ->get('/attendance/report');

        // エラーにならず正常に表示されることを確認する
        $response->assertOk();

        // すべての基本サマリーが0であることを確認する
        $response->assertViewHas('summary', [
            'total_work_minutes' => 0,
            'total_overtime_minutes' => 0,
            'avg_work_minutes' => 0,
        ]);

        // 勤怠がなくても6ヶ月分の0データが返ることを確認する
        $response->assertViewHas('monthlyTrend', function ($trend) {
            return $trend->count() === 6
                && $trend->every(function ($month) {
                    return $month['work_minutes'] === 0
                        && $month['overtime_minutes'] === 0;
                });
        });

        // 異常検知がすべて0件であることを確認する
        $response->assertViewHas('anomalies', [
            'late_count' => 0,
            'early_leave_count' => 0,
            'long_work_count' => 0,
        ]);
    }
}
