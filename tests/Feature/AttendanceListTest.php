<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AttendanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

// ===============================================================
// No.9 勤怠一覧情報取得機能テスト
// ===============================================================

class AttendanceListTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 自分の勤怠情報だけが一覧に表示される
     */
    public function test_only_own_attendance_records_are_displayed(): void
    {
        // 一般ユーザーを2人作成する
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // 自分の勤怠データを作成する
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-11',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 他のユーザーの勤怠データを作成する
        AttendanceRecord::create([
            'user_id' => $otherUser->id,
            'date' => '2026-10-12',
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
        ]);

        // 自分のアカウントで勤怠一覧画面を表示する
        $response = $this->actingAs($user)
            ->get('/attendance/list?date=2026-10');

        // 画面が正常に表示されることを確認する
        $response->assertStatus(200);

        // 自分の勤怠情報が表示されることを確認する
        $response->assertSee('10/11');
        $response->assertSee('09:00');
        $response->assertSee('18:00');

        // 他のユーザーの勤怠情報が表示されないことを確認する
        $response->assertDontSee('10/12');
        $response->assertDontSee('19:00');
    }

    /**
     * 当月の勤怠情報が表示される
     */
    public function test_current_month_attendance_records_are_displayed(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // 現在の日付を2026年10月に固定する
        Carbon::setTestNow(Carbon::parse('2026-10-15'));

        // 当月の勤怠データを作成する
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-11',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 前月の勤怠データを作成する
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-09-10',
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
        ]);

        // 日付指定なしで勤怠一覧画面を表示する
        $response = $this->actingAs($user)->get('/attendance/list');

        // 画面が正常に表示されることを確認する
        $response->assertStatus(200);

        // 当月の年月が表示されることを確認する
        $response->assertSee('2026/10');

        // 当月の勤怠情報が表示されることを確認する
        $response->assertSee('10/11');

        // 前月の勤怠情報が表示されないことを確認する
        $response->assertDontSee('09/10');

        // テスト用の日時設定を解除する
        Carbon::setTestNow();
    }

    /**
     * 前月ボタンで前月の勤怠情報を表示できる
     */
    public function test_previous_month_attendance_records_are_displayed(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // 前月の勤怠データを作成する
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-09-10',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 10月の勤怠一覧画面を表示する
        $response = $this->actingAs($user)
            ->get('/attendance/list?date=2026-10');

        // 前月ボタンのリンク先が9月であることを確認する
        $response->assertSee('href="?date=2026-09"', false);

        // 前月ボタンのリンク先にアクセスする
        $response = $this->get('/attendance/list?date=2026-09');

        // 前月の年月が表示されることを確認する
        $response->assertSee('2026/09');

        // 前月の勤怠情報が表示されることを確認する
        $response->assertSee('09/10');
    }

    /**
     * 翌月ボタンで翌月の勤怠情報を表示できる
     */
    public function test_next_month_attendance_records_are_displayed(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // 翌月の勤怠データを作成する
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-10',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 10月の勤怠一覧画面を表示する
        $response = $this->actingAs($user)
            ->get('/attendance/list?date=2026-10');

        // 翌月ボタンのリンク先が11月であることを確認する
        $response->assertSee('href="?date=2026-11"', false);

        // 翌月ボタンのリンク先にアクセスする
        $response = $this->get('/attendance/list?date=2026-11');

        // 翌月の年月が表示されることを確認する
        $response->assertSee('2026/11');

        // 翌月の勤怠情報が表示されることを確認する
        $response->assertSee('11/10');
    }

    /**
     * 詳細リンクから勤怠詳細画面を表示できる
     */
    public function test_attendance_detail_page_can_be_accessed(): void
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

        // 勤怠一覧画面を表示する
        $response = $this->actingAs($user)
            ->get('/attendance/list?date=2026-10');

        // 作成した勤怠データの詳細リンクが表示されることを確認する
        $response->assertSee(
            'href="' . url('/attendance/' . $attendance->id) . '"',
            false
        );

        // 詳細リンクの遷移先にアクセスする
        $response = $this->get('/attendance/' . $attendance->id);

        // 勤怠詳細画面が正常に表示されることを確認する
        $response->assertStatus(200);
    }
}