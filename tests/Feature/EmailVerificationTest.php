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
     * 会員登録時にメール認証通知が送信されることを確認する。
     */
    public function test_verification_email_is_sent_after_registration(): void
    {
        // 実際にメールを送信せず、通知の送信履歴を記録する
        Notification::fake();

        // 会員登録のリクエストを送信する
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'verify@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // 会員登録後にメール認証画面へ移動することを確認する
        $response->assertRedirect(route('verification.notice'));

        // 登録されたユーザーをデータベースから取得する
        $user = User::where('email', 'verify@example.com')->firstOrFail();

        // メール認証通知が送信されたことを確認する
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    /**
     * 新規登録したユーザーがメール未認証であることを確認する。
     */
    public function test_new_user_is_not_verified(): void
    {
        // 実際のメール送信を防ぐ
        Notification::fake();

        // 新規ユーザーを登録する
        $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'unverified@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // 登録されたユーザーを取得する
        $user = User::where('email', 'unverified@example.com')->firstOrFail();

        // メール認証日時が登録されていないことを確認する
        $this->assertNull($user->email_verified_at);

        // メール認証が完了していないことを確認する
        $this->assertFalse($user->hasVerifiedEmail());
    }

    /**
     * 正しい認証リンクでメール認証が完了することを確認する。
     */
    public function test_user_can_verify_email(): void
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
        $this->actingAs($user);

        // メール認証リンクにアクセスする
        $response = $this->get($verificationUrl);

        // 認証処理が成功してリダイレクトされることを確認する
        $response->assertRedirect();

        // データベースから最新の認証状態を取得する
        $user->refresh();

        // メール認証が完了していることを確認する
        $this->assertTrue($user->hasVerifiedEmail());

        // メール認証日時が保存されていることを確認する
        $this->assertNotNull($user->email_verified_at);
    }

    /**
     * 不正な認証リンクではメール認証されないことを確認する。
     */
    public function test_user_cannot_verify_email_with_invalid_link(): void
    {
        // メール未認証のユーザーを作成する
        $user = User::factory()->unverified()->create();

        // 正しい署名付き認証URLを作成する
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        // URLの末尾に余分なパラメータを追加して署名を無効にする
        $invalidUrl = $verificationUrl . '&invalid=1';

        // 作成したユーザーとしてログインする
        $this->actingAs($user);

        // 不正な認証リンクにアクセスする
        $response = $this->get($invalidUrl);

        // 不正な署名によって403エラーになることを確認する
        $response->assertForbidden();

        // データベースから最新の認証状態を取得する
        $user->refresh();

        // メール認証が完了していないことを確認する
        $this->assertFalse($user->hasVerifiedEmail());
    }

    /**
     * メール未認証ユーザーが勤怠画面にアクセスできないことを確認する
     */
    public function test_unverified_user_is_redirected_to_verification_notice(): void
    {
        // メール未認証のユーザーを作成する
        $user = User::factory()->unverified()->create();

        // 作成したユーザーとしてログインする
        $this->actingAs($user, 'web');

        // 勤怠画面にアクセスする
        $response = $this->get('/attendance');

        // メール認証画面へリダイレクトされることを確認する
        $response->assertRedirect(route('verification.notice'));
    }

    /**
     * メール認証済みユーザーが勤怠画面にアクセスできることを確認する
     */
    public function test_verified_user_can_access_attendance(): void
    {
        // メール認証済みのユーザーを作成する
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // 作成したユーザーとしてログインする
        $this->actingAs($user, 'web');

        // 勤怠画面にアクセスする
        $response = $this->get('/attendance');

        // 勤怠画面が正常に表示されることを確認する
        $response->assertOk();
    }

    /**
     * 未認証ユーザーが認証メールを再送できることを確認する
     */
    public function test_unverified_user_can_resend_verification_email(): void
    {
        // メール送信を実際には行わず、通知を記録する
        Notification::fake();

        // メール未認証のユーザーを作成する
        $user = User::factory()->unverified()->create();

        // 作成したユーザーとしてログインする
        $this->actingAs($user, 'web');

        // 認証メールの再送リクエストを送信する
        $response = $this->post(route('verification.send'));

        // 再送処理後に元の画面へリダイレクトされることを確認する
        $response->assertRedirect();

        // 再送完了のステータスがセッションに保存されることを確認する
        $response->assertSessionHas('status', 'verification-link-sent');

        // 認証メールが1件送信されたことを確認する
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    /**
     * 認証済みユーザーには認証メールを再送しないことを確認する
     */
    public function test_verified_user_does_not_receive_verification_email_again(): void
    {
        // メール送信を実際には行わず、通知を記録する
        Notification::fake();

        // メール認証済みのユーザーを作成する
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // 作成したユーザーとしてログインする
        $this->actingAs($user, 'web');

        // 認証メールの再送リクエストを送信する
        $response = $this->post(route('verification.send'));

        // 処理後にリダイレクトされることを確認する
        $response->assertRedirect();

        // 認証メールが送信されていないことを確認する
        Notification::assertNotSentTo($user, VerifyEmail::class);
    }
}
