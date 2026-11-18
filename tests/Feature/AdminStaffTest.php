<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStaffTest extends TestCase
{
    use RefreshDatabase;

    // 管理者がスタッフ一覧で全ユーザーの氏名とメールアドレスを確認できる
    public function test_admin_can_see_all_staff_information(): void
    {
        // 管理者を作成する
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        // 一般ユーザーを2人作成する
        $user1 = User::factory()->create([
            'name' => 'テストユーザー1',
            'email' => 'staff1@example.com',
            'admin_status' => false,
        ]);

        $user2 = User::factory()->create([
            'name' => 'テストユーザー2',
            'email' => 'staff2@example.com',
            'admin_status' => false,
        ]);

        // 管理者としてログインする
        $this->actingAs($admin, 'admin');

        // スタッフ一覧画面を開く
        $response = $this->get('/admin/staff/list');

        // 正常に画面が表示されることを確認する
        $response->assertOk();

        // 一般ユーザーの氏名とメールアドレスを確認する
        $response->assertSee('テストユーザー1');
        $response->assertSee('staff1@example.com');
        $response->assertSee('テストユーザー2');
        $response->assertSee('staff2@example.com');
    }

    // 指定スタッフの勤怠情報が正しく表示される
    public function test_admin_can_see_staff_attendance(): void
    {
        // 管理者を作成する
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        // 勤怠確認用の一般ユーザーを作成する
        $user = User::factory()->create([
            'name' => '勤怠確認ユーザー',
            'admin_status' => false,
        ]);

        // 対象ユーザーの勤怠を登録する
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-10',
            'clock_in' => '09:00',
            'clock_out' => '18:00',
        ]);

        // 管理者としてログインする
        $this->actingAs($admin, 'admin');

        // 対象スタッフの11月の勤怠一覧を開く
        $response = $this->get(
            "/admin/attendance/staff/{$user->id}?date=2026-11"
        );

        // 正常に表示されることを確認する
        $response->assertOk();

        // スタッフ名と勤怠時刻を確認する
        $response->assertSee('勤怠確認ユーザー');
        $response->assertSee('11/10');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    // 前月の勤怠情報が表示される
    public function test_admin_can_see_previous_month_attendance(): void
    {
        // 管理者を作成する
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        // 一般ユーザーを作成する
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        // 前月の勤怠を登録する
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-15',
            'clock_in' => '08:30',
            'clock_out' => '17:30',
        ]);

        // 管理者としてログインする
        $this->actingAs($admin, 'admin');

        // 10月の勤怠一覧を開く
        $response = $this->get(
            "/admin/attendance/staff/{$user->id}?date=2026-10"
        );

        // 正常に表示されることを確認する
        $response->assertOk();

        // 前月の勤怠時刻が表示されることを確認する
        $response->assertSee('08:30');
        $response->assertSee('17:30');

        // 前月へのリンクが存在することを確認する
        $response->assertSee('date=2026-09', false);
    }

    // 翌月の勤怠情報が表示される
    public function test_admin_can_see_next_month_attendance(): void
    {
        // 管理者を作成する
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        // 一般ユーザーを作成する
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        // 翌月の勤怠を登録する
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-12-15',
            'clock_in' => '10:00',
            'clock_out' => '19:00',
        ]);

        // 管理者としてログインする
        $this->actingAs($admin, 'admin');

        // 12月の勤怠一覧を開く
        $response = $this->get(
            "/admin/attendance/staff/{$user->id}?date=2026-12"
        );

        // 正常に表示されることを確認する
        $response->assertOk();

        // 翌月の勤怠時刻が表示されることを確認する
        $response->assertSee('10:00');
        $response->assertSee('19:00');

        // 翌月へのリンクが存在することを確認する
        $response->assertSee('date=2027-01', false);
    }

    // スタッフの勤怠詳細画面に遷移できる
    public function test_admin_can_access_staff_attendance_detail(): void
    {
        // 管理者を作成する
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        // 一般ユーザーを作成する
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        // 勤怠データを作成する
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-10',
            'clock_in' => '09:00',
            'clock_out' => '18:00',
        ]);

        // 管理者としてログインする
        $this->actingAs($admin, 'admin');

        // スタッフの月次勤怠一覧を開く
        $response = $this->get(
            "/admin/attendance/staff/{$user->id}?date=2026-11"
        );

        // 正常に表示されることを確認する
        $response->assertOk();

        // 勤怠詳細へのリンクが存在することを確認する
        $response->assertSee(
            '/attendance/' . $attendance->id,
            false
        );

        // 詳細画面を開く
        $detailResponse = $this->get(
            '/attendance/' . $attendance->id
        );

        // 詳細画面が正常に表示されることを確認する
        $detailResponse->assertOk();
    }
}
