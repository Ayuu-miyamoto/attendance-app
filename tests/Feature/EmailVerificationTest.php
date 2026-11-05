<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    // テストごとにデータベースを初期化する
    use RefreshDatabase;

    /**
     * 会員登録後、認証メールが送信されることを確認する。
     */
    public function test_verification_email_is_sent_after_registration(): void
    {
        // 実際にはメールを送信せず、通知の送信履歴を記録する
        Notification::fake();

        // 会員登録のリクエストを送信する
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'verify@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // 会員登録後にメール認証誘導画面へ遷移することを確認する
        $response->assertRedirect(route('verification.notice'));

        // 登録されたユーザーをデータベースから取得する
        $user = User::where('email', 'verify@example.com')->firstOrFail();

        // 登録したメールアドレスが正しいことを確認する
        $this->assertEquals('verify@example.com', $user->email);

        // 登録したユーザーに認証メール通知が送信されたことを確認する
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    /**
     * 認証誘導画面からメール認証サイトへ移動できることを確認する。
     */
    public function test_verification_notice_has_mailpit_link(): void
    {
        // メール未認証のユーザーを作成する
        $user = User::factory()->unverified()->create();

        // 作成したユーザーとしてログインする
        $this->actingAs($user, 'web');

        // メール認証誘導画面にアクセスする
        $response = $this->get(route('verification.notice'));

        // 認証誘導画面が正常に表示されることを確認する
        $response->assertOk();

        // Mailpitへのリンクが設定されていることを確認する
        $response->assertSee('href="http://localhost:8025"', false);

        // 「認証はこちらから」という文字が表示されることを確認する
        $response->assertSee('認証はこちらから');
    }

    /**
     * メール認証完了後、勤怠登録画面へ遷移することを確認する。
     */
    public function test_user_can_verify_email_and_redirect_to_attendance(): void
    {
        // メール未認証のユーザーを作成する
        $user = User::factory()->unverified()->create();

        // メール認証用の署名付きURLを作成する
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        // 作成したユーザーとしてログインする
        $this->actingAs($user, 'web');

        // メール認証リンクにアクセスする
        $response = $this->get($verificationUrl);

        // 認証完了後に勤怠登録画面へ遷移することを確認する
        $response->assertRedirect('/attendance?verified=1');

        // データベースから最新のユーザー情報を取得する
        $user->refresh();

        // メール認証が完了していることを確認する
        $this->assertTrue($user->hasVerifiedEmail());

        // メール認証日時が保存されていることを確認する
        $this->assertNotNull($user->email_verified_at);

        // 認証済みユーザーとして勤怠登録画面にアクセスする
        $attendanceResponse = $this->get('/attendance');

        // 勤怠登録画面が正常に表示されることを確認する
        $attendanceResponse->assertOk();
    }
}
