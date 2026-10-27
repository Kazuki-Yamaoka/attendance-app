<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserAuthenticationTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */

    /** @test */
    public function ユーザー登録で名前が未入力の場合はバリデーションエラーになる() 
    {
        // 1. 名前以外のユーザー情報を入力する（準備）
        // 2. 会員登録の処理を行う
        $response = $this->post('/register', [
            'name' => '', // 未入力
            'email' => 'test@example.com', 
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    /** @test */
    public function ユーザー登録でメールアドレスが未入力の場合はバリデーションエラーになる() 
    {
        // 1. メールアドレス以外のユーザー情報を入力する（準備）
        // 2. 会員登録の処理を行う
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => '', // 未入力
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    /** @test */
    public function ユーザー登録でパスワードが8文字未満の場合はバリデーションエラーになる() 
    {
        // 1. パスワード以外を正しく入力し、パスワードを8文字未満にする（準備）
        // 2. 会員登録の処理を行う
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => '1234567', // 7文字（8文字未満）
            'password_confirmation' => '1234567',
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    /** @test */
    public function ユーザー登録でパスワードが一致しない場合はバリデーションエラーになる() 
    {
        // 1. パスワードと確認用パスワードに異なる値を入力する（準備）
        // 2. 会員登録の処理を行う
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different_password', // 不一致
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    /** @test */
    public function ユーザー登録でパスワードが未入力の場合はバリデーションエラーになる() 
    {
        // 1. パスワードを空にして他の情報を入力する（準備）
        // 2. 会員登録の処理を行う
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => '', // 未入力
            'password_confirmation' => '',
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    /** @test */
    public function フォームに内容が入力されていた場合はデータが正常に保存される() 
    {
        // 1. すべてのユーザー情報を正しく入力する（準備）
        // 2. 会員登録の処理を行う
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // データベースにデータが正常に保存されているか確認
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'テストユーザー',
        ]);

        // 登録成功後に適切な画面へリダイレクトされるか
        $response->assertRedirect();
    }

    /** @test */
    public function 一般ログインでメールアドレスが未入力の場合はバリデーションエラーになる() 
    {
        // 【設計の指示通り】事前にユーザーを登録しておく
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => '', // 空
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    /** @test */
    public function 一般ログインでパスワードが未入力の場合はバリデーションエラーになる() 
    {
        // 【設計の指示通り】事前にユーザーを登録しておく
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => '', // 空
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    /** @test */
    public function 一般ログインで登録内容と一致しない場合はバリデーションエラーになる() 
    {
        // 【設計の指示通り】事前にユーザーを登録しておく
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('correct_password'),
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrong_password', // 間違ったパスワード
        ]);

        $response->assertSessionHasErrors(['email']);
    }
}
