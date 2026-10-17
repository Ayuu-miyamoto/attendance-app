<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AttendanceRecord;
use App\Models\AttendanceBreak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 現在の日付・時刻がUIと同じ形式で表示される
     */
    public function test_current_date_and_time_are_displayed(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow(Carbon::parse('2026-10-11 14:30:00'));

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('2026年10月11日(日)');
        $response->assertSee('14:30');

        Carbon::setTestNow();
    }

    /**
     * 勤怠記録がない場合、勤務外と表示される
     */
    public function test_status_is_off_duty_without_attendance_record(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('勤務外');
    }

    /**
     * 出勤済みで退勤していない場合、出勤中と表示される
     */
    public function test_status_is_working_after_clock_in(): void
    {
        $user = User::factory()->create();

        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => today(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('出勤中');
    }

    /**
     * 休憩中の場合、休憩中と表示される
     */
    public function test_status_is_on_break_during_break(): void
    {
        $user = User::factory()->create();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => today(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        AttendanceBreak::create([
            'attendance_record_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => null,
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('休憩中');
    }

    /**
     * 退勤済みの場合、退勤済と表示される
     */
    public function test_status_is_finished_after_clock_out(): void
    {
        $user = User::factory()->create();

        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => today(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('退勤済');
    }

    /**
     * 出勤操作で出勤時刻が保存される
     */
    public function test_clock_in_saves_attendance_record(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow(Carbon::parse('2026-10-11 09:00:00'));

        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'clock_in',
        ]);

        $response->assertRedirect('/attendance');

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'date' => '2026-10-11 00:00:00',
            'clock_in' => '09:00:00',
        ]);

        Carbon::setTestNow();
    }

    /**
     * 出勤は一日に一回のみできる
     */
    public function test_clock_in_can_only_be_done_once_per_day(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow(Carbon::parse('2026-10-11 09:00:00'));

        $this->actingAs($user)->post('/attendance', [
            'action' => 'clock_in',
        ]);

        $this->post('/attendance', [
            'action' => 'clock_in',
        ]);

        $this->assertSame(
            1,
            AttendanceRecord::where('user_id', $user->id)
                ->whereDate('date', '2026-10-11')
                ->count()
        );

        Carbon::setTestNow();
    }

    /**
     * 出勤時刻が勤怠一覧画面で確認できる
     */
    public function test_clock_in_time_is_displayed_on_attendance_list(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow(Carbon::parse('2026-10-11 09:00:00'));

        $this->actingAs($user)->post('/attendance', [
            'action' => 'clock_in',
        ]);

        $response = $this->get('/attendance/list?date=2026-10');

        $response->assertStatus(200);
        $response->assertSee('09:00');

        Carbon::setTestNow();
    }

    /**
     * 休憩入の操作で休憩開始時刻が保存される
     */
    public function test_break_in_saves_break_start_time(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow(Carbon::parse('2026-10-11 09:00:00'));

        $this->actingAs($user)->post('/attendance', [
            'action' => 'clock_in',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-11 12:00:00'));

        $this->post('/attendance', [
            'action' => 'break_in',
        ]);

        $this->assertDatabaseHas('breaks', [
            'break_in' => '12:00:00',
            'break_out' => null,
        ]);

        $response = $this->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('休憩中');

        Carbon::setTestNow();
    }

    /**
     * 休憩戻の操作で休憩終了時刻が保存される
     */
    public function test_break_out_saves_break_end_time(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Carbon::setTestNow(Carbon::parse('2026-10-11 09:00:00'));

        $this->post('/attendance', [
            'action' => 'clock_in',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-11 12:00:00'));

        $this->post('/attendance', [
            'action' => 'break_in',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-11 13:00:00'));

        $this->post('/attendance', [
            'action' => 'break_out',
        ]);

        $this->assertDatabaseHas('breaks', [
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $response = $this->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('出勤中');

        Carbon::setTestNow();
    }

    /**
     * 休憩は一日に何回でもできる
     */
    public function test_multiple_breaks_can_be_taken_in_one_day(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Carbon::setTestNow(Carbon::parse('2026-10-11 09:00:00'));

        $this->post('/attendance', [
            'action' => 'clock_in',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-11 12:00:00'));

        $this->post('/attendance', [
            'action' => 'break_in',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-11 13:00:00'));

        $this->post('/attendance', [
            'action' => 'break_out',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-11 15:00:00'));

        $this->post('/attendance', [
            'action' => 'break_in',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-11 15:30:00'));

        $this->post('/attendance', [
            'action' => 'break_out',
        ]);

        $this->assertDatabaseCount('breaks', 2);

        $this->assertDatabaseHas('breaks', [
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $this->assertDatabaseHas('breaks', [
            'break_in' => '15:00:00',
            'break_out' => '15:30:00',
        ]);

        Carbon::setTestNow();
    }

    /**
     * 退勤操作で退勤時刻が保存され、退勤済と表示される
     */
    public function test_clock_out_saves_attendance_end_time(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Carbon::setTestNow(Carbon::parse('2026-10-11 09:00:00'));

        $this->post('/attendance', [
            'action' => 'clock_in',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-11 18:00:00'));

        $this->post('/attendance', [
            'action' => 'clock_out',
        ]);

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'date' => '2026-10-11 00:00:00',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('退勤済');
        $response->assertSee('お疲れ様でした。');

        Carbon::setTestNow();
    }

    /**
     * 退勤時刻が勤怠一覧画面で確認できる
     */
    public function test_clock_out_time_is_displayed_on_attendance_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Carbon::setTestNow(Carbon::parse('2026-10-11 09:00:00'));

        $this->post('/attendance', [
            'action' => 'clock_in',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-11 18:00:00'));

        $this->post('/attendance', [
            'action' => 'clock_out',
        ]);

        $response = $this->get('/attendance/list?date=2026-10');

        $response->assertStatus(200);
        $response->assertSee('09:00');
        $response->assertSee('18:00');

        Carbon::setTestNow();
    }

    /**
     * 休憩時間の合計が勤怠一覧画面で確認できる
     */
    public function test_total_break_time_is_displayed_on_attendance_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Carbon::setTestNow(Carbon::parse('2026-10-11 09:00:00'));

        $this->post('/attendance', [
            'action' => 'clock_in',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-11 12:00:00'));

        $this->post('/attendance', [
            'action' => 'break_in',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-11 13:00:00'));

        $this->post('/attendance', [
            'action' => 'break_out',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-11 18:00:00'));

        $this->post('/attendance', [
            'action' => 'clock_out',
        ]);

        $response = $this->get('/attendance/list?date=2026-10');

        $response->assertStatus(200);
        $response->assertSee('1:00');

        Carbon::setTestNow();
    }
}