<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;
    
    /**
     * 一般ユーザー（新規登録）
     */

    // 登録画面を表示できる
    public function test_user_register_page(): void
    {
        $response = $this->get('/register');

        // $response=アクセスした結果（レスポンス）を受け取ったもの
        // HTTPステータスコードが 200(=正常に表示できた) か確認する
        $response->assertStatus(200);
    }

    // 正しい情報で会員登録できる
    public function test_user_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // 登録後、/attendance にリダイレクトされることを確認する
        $response->assertRedirect('/attendance');

        // データベースに登録されていることを確認する
        $this->assertDatabaseHas('users', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
        ]); 
    }

    // 名前・メールアドレス・パスワードが未入力の場合のバリデーションを確認する
    public function test_user_register_required(): void
    {
        // 必須項目を空欄にして会員登録処理を実行する
        $response = $this->post('/register', [
            'name' => '',
            'email' => '',
            'password' => '',
            'password_confirmation' => '',
        ]);

        // 仕様書どおりのエラーメッセージが返されることを確認する
        $response->assertSessionHasErrors([
            'name' => 'お名前を入力してください',
            'email' => 'メールアドレスを入力してください',
            'password' => 'パスワードを入力してください',
        ]);
    }

    // メール形式ではない場合、バリデーションエラーになる
    public function test_user_register_invalid_email(): void
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'invalid-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // メール形式ではない場合、バリデーションエラーになることを確認する
        $response->assertSessionHasErrors([
            'email',
        ]);
    }

    // パスワードが8文字未満の場合、バリデーションエラーになる
    public function test_user_register_short_password(): void
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => '1234567',
            'password_confirmation' => '1234567',
        ]);

        $response->assertSessionHasErrors([
            'password' => 'パスワードは8文字以上で入力してください',
        ]);
    }

   // 確認用パスワードが一致しない場合、バリデーションエラーになる
    public function test_user_register_password_confirmation(): void
    {
        // パスワードと確認用パスワードに異なる値を設定する
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
        ]);

        // パスワードが一致しない場合、仕様書どおりのエラーメッセージが返されることを確認する
        $response->assertSessionHasErrors([
            'password' => 'パスワードと一致しません',
        ]);
    }

    /** 
     * 一般ユーザー（ログイン）
     */

    // ログイン画面を表示できる
    public function test_user_login_page(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    // 未入力の場合、バリデーションエラーになる
    public function test_user_login_required(): void
    {
        $response = $this->post('/login', [
            'email' => '',
            'password' => '',
        ]);

        // メールアドレスとパスワードが未入力の場合、
    // 仕様書どおりのエラーメッセージが返されることを確認する
    $response->assertSessionHasErrors([
        'email' => 'メールアドレスを入力してください',
        'password' => 'パスワードを入力してください',
        ]);
    }

    // 間違った情報ではログインできない
    // 間違った情報ではログインできない
    public function test_user_login_failed(): void
    {
        // 間違ったメールアドレスとパスワードでログインする
        $response = $this->post('/login', [
            'email' => 'wrong@example.com',
            'password' => 'wrongpassword',
        ]);

        // 仕様書どおりのエラーメッセージが返されることを確認する
        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);

        // ログインしていないことを確認する
        $this->assertGuest();
    }

    // 正しい情報でログインできる
    public function test_user_login(): void
    {
        $user = User::factory()->create([
            'email' => 'user1@example.com',
            'password' => bcrypt('password123'),
            'admin_status' => false,
        ]);

        $response = $this->post('/login', [
            'email' => 'user1@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/attendance');

        $this->assertAuthenticatedAs($user);
    }

    /**
    * 管理者（ログイン）
    */
    
    // ログイン画面を表示できる
    public function test_admin_login_page(): void
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
    }

    // 未入力の場合、バリデーションエラーになる
    public function test_admin_login_required(): void
    {
        $response = $this->post('/admin/login', [
            'email' => '',
            'password' => '',
        ]);

        // メールアドレスとパスワードが未入力の場合、
        // 仕様書どおりのエラーメッセージが返されることを確認する
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
            'password' => 'パスワードを入力してください',
        ]);
    }

    // 間違った情報ではログインできない
    public function test_admin_login_failed(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'wrong@example.com',
            'password' => 'wrongpassword',
        ]);

        // ログイン情報が一致しない場合、
        // 仕様書どおりのエラーメッセージが返されることを確認する
        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);

        $this->assertGuest();
    }

    // 正しい情報でログインできる
    public function test_admin_login(): void
    {
        $admin = User::factory()->create([
            'email' => 'user3@example.com',
            'password' => bcrypt('password123'),
            'admin_status' => true,
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'user3@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/admin/attendance/list');

        // adminガードで管理者として認証されていることを確認
        $this->assertAuthenticatedAs($admin, 'admin');
    }
}