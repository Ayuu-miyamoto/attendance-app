<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationAccessTest extends TestCase
{
    // テストごとにデータベースを初期化する
    use RefreshDatabase;

    // 未認証ユーザーが勤怠修正を送信できないことを確認する
    public function test_unverified_user_cannot_submit_attendance_correction(): void
    {
        // メール未認証の一般ユーザーを作成する
        $user = User::factory()->unverified()->create();

        // 一般ユーザーとしてログインする
        $this->actingAs($user, 'web');

        // 勤怠修正処理にPOSTリクエストを送信する
        $response = $this->post('/attendance/1', []);

        // メール認証案内画面へリダイレクトされることを確認する
        $response->assertRedirect(route('verification.notice'));
    }

    // 未認証ユーザーが修正申請一覧を表示できないことを確認する
    public function test_unverified_user_cannot_view_correction_request_list(): void
    {
        // メール未認証の一般ユーザーを作成する
        $user = User::factory()->unverified()->create();

        // 一般ユーザーとしてログインする
        $this->actingAs($user, 'web');

        // 修正申請一覧画面にGETリクエストを送信する
        $response = $this->get('/stamp_correction_request/list');

        // メール認証案内画面へリダイレクトされることを確認する
        $response->assertRedirect(route('verification.notice'));
    }

    // 未認証ユーザーが修正申請詳細へ進めないことを確認する
    public function test_unverified_user_cannot_view_correction_request_detail(): void
    {
        // メール未認証の一般ユーザーを作成する
        $user = User::factory()->unverified()->create();

        // 一般ユーザーとしてログインする
        $this->actingAs($user, 'web');

        // 修正申請詳細への振り分けルートにGETリクエストを送信する
        $response = $this->get('/application/1');

        // メール認証案内画面へリダイレクトされることを確認する
        $response->assertRedirect(route('verification.notice'));
    }

    // 未ログインユーザーが修正申請一覧を表示できないことを確認する
    public function test_guest_cannot_view_correction_request_list(): void
    {
        // ログインせずに修正申請一覧へアクセスする
        $response = $this->get('/stamp_correction_request/list');

        // ログイン画面へリダイレクトされることを確認する
        $response->assertRedirect('/login');
    }

    // メール認証済みユーザーが修正申請一覧を表示できることを確認する
    public function test_verified_user_can_view_correction_request_list(): void
    {
        // メール認証済みの一般ユーザーを作成する
        $user = User::factory()->create();

        // 一般ユーザーとしてログインする
        $this->actingAs($user, 'web');

        // 修正申請一覧画面にアクセスする
        $response = $this->get('/stamp_correction_request/list');

        // 正常に画面が表示されることを確認する
        $response->assertOk();
    }
}