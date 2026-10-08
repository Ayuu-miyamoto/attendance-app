<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ユーザー登録が成功するかテスト
     */
    public function test_user_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => '山田太郎',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');

        $this->assertDatabaseHas('users', [
            'email' => 'taro@example.com'
        ]);

        $this->assertAuthenticated();
    }
}
