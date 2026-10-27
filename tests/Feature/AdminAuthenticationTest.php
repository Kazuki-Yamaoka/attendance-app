<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */

    /** @test */
    public function 管理者ログインでメールアドレスが未入力の場合はバリデーションエラーになる() 
    {
        // 【設計の指示通り】事前に管理者ユーザーを登録しておく
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'is_admin' => true,
        ]);

        $response = $this->post('/admin/login', [
            'email' => '', // 空
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    /** @test */
    public function 管理者ログインでパスワードが未入力の場合はバリデーションエラーになる() 
    {
        // 【設計の指示通り】事前に管理者ユーザーを登録しておく
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'is_admin' => true,
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => '', // 空
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    /** @test */
    public function 管理者ログインで登録内容と一致しない場合はバリデーションエラーになる() 
    {
        // 【設計の指示通り】事前に管理者ユーザーを登録しておく
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('correct_password'),
            'is_admin' => true,
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong_password', // 間違ったパスワード
        ]);

        $response->assertSessionHasErrors(['email']);
    }
}
