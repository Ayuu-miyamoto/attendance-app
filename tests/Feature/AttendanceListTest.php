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
    // テストごとにDBを初期化する
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

        // 未出勤日も日付だけは表示されることを確認する
        $response->assertSee('10/12');

        // 他のユーザーの出退勤時刻が表示されないことを確認する
        $response->assertDontSee('19:00');
        $response->assertDontSee('10:00');

        // 他のユーザーの勤怠レコードIDが一覧に含まれないことを確認する
        $response->assertViewHas(
            'formattedAttendanceRecords',
            function (array $records) use ($otherUser): bool {

                // 他のユーザーの勤怠IDを取得する
                $otherAttendanceIds = AttendanceRecord::where(
                    'user_id',
                    $otherUser->id
                )->pluck('id')->all();

                // 他のユーザーの勤怠IDが一覧に存在しないことを確認する
                return collect($records)->every(
                    fn (array $record): bool =>
                        !in_array($record['id'], $otherAttendanceIds, true)
                );
            }
        );
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

        try {

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
            $response = $this->actingAs($user)
                ->get('/attendance/list');

            // 画面が正常に表示されることを確認する
            $response->assertStatus(200);

            // 当月の年月が表示されることを確認する
            $response->assertSee('2026/10');

            // 当月の勤怠情報が表示されることを確認する
            $response->assertSee('10/11');

            // 前月の勤怠情報が表示されないことを確認する
            $response->assertDontSee('09/10');

        } finally {

            // テスト用の日時設定を解除する
            Carbon::setTestNow();
        }
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

    /**
     * 勤怠がない日も一覧に表示される
     */
    public function test_days_without_attendance_are_displayed(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // 10月11日だけ勤怠データを作成する
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-11',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 10月の勤怠一覧画面を表示する
        $response = $this->actingAs($user)
            ->get('/attendance/list?date=2026-10');

        // 画面が正常に表示されることを確認する
        $response->assertStatus(200);

        // 月初の日付が表示されることを確認する
        $response->assertSee('10/01');

        // 勤怠が存在する日が表示されることを確認する
        $response->assertSee('10/11');

        // 勤怠が存在しない日が表示されることを確認する
        $response->assertSee('10/12');

        // 月末の日付が表示されることを確認する
        $response->assertSee('10/31');

        // 10月の31日分が配列に存在することを確認する
        $response->assertViewHas(
            'formattedAttendanceRecords',
            function (array $records): bool {

                // 31件であれば成功とする
                return count($records) === 31;
            }
        );
    }

    /**
     * 未出勤日は出退勤時刻が空欄になる
     */
    public function test_day_without_attendance_has_empty_times(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // 10月の勤怠一覧画面を表示する
        $response = $this->actingAs($user)
            ->get('/attendance/list?date=2026-10');

        // 画面が正常に表示されることを確認する
        $response->assertStatus(200);

        // 未出勤日の表示情報を確認する
        $response->assertViewHas(
            'formattedAttendanceRecords',
            function (array $records): bool {

                // 10月12日の表示データを取得する
                $day = collect($records)->firstWhere(
                    'date',
                    '10/12'
                );

                // 該当日が存在しなければ失敗とする
                if (!$day) {
                    return false;
                }

                // 出退勤と休憩・合計が空欄であることを確認する
                return $day['clock_in'] === ''
                    && $day['clock_out'] === ''
                    && $day['total_break_time'] === null
                    && $day['total_time'] === null;
            }
        );
    }

    /**
     * 未出勤日の詳細リンクから詳細画面を表示できる
     */
    public function test_day_without_attendance_detail_can_be_accessed(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // 10月の勤怠一覧画面を表示する
        $response = $this->actingAs($user)
            ->get('/attendance/list?date=2026-10');

        // 未出勤日の詳細リンクが表示されることを確認する
        $response->assertSee(
            'href="' . url('/attendance/2026-10-12') . '"',
            false
        );

        // 未出勤日の詳細画面にアクセスする
        $response = $this->get('/attendance/2026-10-12');

        // 詳細画面が正常に表示されることを確認する
        $response->assertStatus(200);

        // 未出勤日の日付が表示されることを確認する
        $response->assertSee('10月12日');

        // 詳細画面に空欄の出退勤時刻が渡されることを確認する
        $response->assertViewHas(
            'data',
            function (array $data): bool {

                // 未出勤日の表示情報が正しいことを確認する
                return $data['id'] === '2026-10-12'
                    && $data['clock_in'] === ''
                    && $data['clock_out'] === ''
                    && $data['application'] === null;
            }
        );

        // 詳細画面の表示だけではDBに勤怠を作成しないことを確認する
        $this->assertDatabaseMissing('attendance_records', [
            'user_id' => $user->id,
            'date' => '2026-10-12',
        ]);
    }

    /**
     * 2月は28日分の勤怠一覧が表示される
     */
    public function test_february_has_28_days(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // 2027年2月の勤怠一覧画面を表示する
        $response = $this->actingAs($user)
            ->get('/attendance/list?date=2027-02');

        // 画面が正常に表示されることを確認する
        $response->assertStatus(200);

        // 2月の28日分が存在することを確認する
        $response->assertViewHas(
            'formattedAttendanceRecords',
            function (array $records): bool {

                // 28件であれば成功とする
                return count($records) === 28;
            }
        );

        // 2月28日が表示されることを確認する
        $response->assertSee('02/28');

        // 存在しない2月29日は表示されないことを確認する
        $response->assertDontSee('02/29');
    }

    /**
     * 勤怠一覧を表示するだけではDBに勤怠データを作成しない
     */
    public function test_attendance_list_does_not_create_records(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create();

        // 勤怠一覧を表示する
        $response = $this->actingAs($user)
            ->get('/attendance/list?date=2026-10');

        // 画面が正常に表示されることを確認する
        $response->assertStatus(200);

        // 表示用の31日分が存在することを確認する
        $response->assertViewHas(
            'formattedAttendanceRecords',
            function (array $records): bool {

                // 31件であれば成功とする
                return count($records) === 31;
            }
        );

        // 勤怠レコードが作成されていないことを確認する
        $this->assertDatabaseCount('attendance_records', 0);
    }
}