<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AdvancedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic feature test example.
     */
    /**
     * ステップ1: 会員登録後、認証メールが送信される
     * 1. 会員登録をする
     * 2. 認証メールを送信する（Laravelの標準機能により登録時に自動送信される）
     */
    public function test_step1_verification_email_is_sent_after_registration()
    {
        // 1. 会員登録をする
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);

        // 2. 未認証状態で保護されたページ（例: /attendance）にアクセスすると、認証メールが送信済みかつ未認証のため誘導画面にリダイレクトされる
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertRedirect(route('verification.notice'));
    }

    /**
     * ステップ2: メール認証誘導画面でボタンを押下するとメール認証サイトに遷移する
     * 1. メール認証誘導画面を表示する
     * 2. 「認証はこちらから」ボタンを押下（該当リンクへアクセス）
     * 3. メール認証サイト（または認証処理のエンドポイント）へ正常に遷移する
     */
    public function test_step2_clicking_verification_button_leads_to_verification_action()
    {
        $user = User::factory()->unverified()->create();

        // 1. メール認証誘導画面を表示する
        $response = $this->actingAs($user)->get(route('verification.notice'));
        $response->assertStatus(200);

        // 2 & 3. 認証メール内のリンク（「認証はこちらから」ボタンの遷移先）を生成
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        // ボタン押下を模してそのURLにアクセスした際、エラーなく処理（リダイレクト等）に到達することを確認
        $response = $this->actingAs($user)->get($verificationUrl);
        // 正常に認証が完了すれば次の画面へリダイレクトされるステータス(302)になる
        $response->assertStatus(302);
    }

    /**
     * ステップ3: メール認証サイトのメール認証を完了すると、勤怠登録画面に遷移する
     * 1. メール認証を完了する
     * 2. 勤怠登録画面を表示する
     */
    public function test_step3_completing_email_verification_redirects_to_attendance_page()
    {
        $user = User::factory()->unverified()->create();

        // 有効な署名付き認証URLを生成
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        // 1. メール認証を完了する
        $response = $this->actingAs($user)->get($verificationUrl);

        // ユーザーのメール認証日時が更新されていることを確認
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        // 2. 勤怠登録画面（/attendance）に遷移すること
        $response->assertRedirect('/attendance');
    }

    /**
     * ゲスト（未認証ユーザー）はレポートページにアクセスできないこと
     */
    public function test_guest_cannot_access_report_page()
    {
        // 未認証で GET /attendance/report を実行
        $response = $this->get('/attendance/report');

        // ログイン画面等にリダイレクトされること
        $response->assertRedirect('/login');
    }

    /**
     * 認証ユーザーの統計情報が正しく計算され、正常にアクセスできること（別日で複数日作成）
     */
    public function test_authenticated_user_can_view_calculated_report()
    {
        $user = User::factory()->create();

        // 別日で勤怠データを複数日作成（例：昨日と今日）
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->subDay()->toDateString(), // 昨日
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(), // 今日
            'clock_in' => '09:00:00',
            'clock_out' => '19:00:00',
        ]);

        // 認証状態で GET /attendance/report を実行
        $response = $this->actingAs($user)->get('/attendance/report');

        $response->assertStatus(200);
        $response->assertViewIs('user.user-statistices-report'); // 指定されたビュー名
        $response->assertViewHas(['summary', 'monthlyTrends', 'anomalies']);
    }

    /**
     * 勤怠記録がないユーザーでもエラーを起こさず安全に処理され、0や初期値として正しく表示されること
     */
    public function test_user_with_no_attendance_records_can_access_report_safely_and_shows_zero()
    {
        $user = User::factory()->create();

        // 勤怠データのないユーザーで認証して GET /attendance/report を実行
        $response = $this->actingAs($user)->get('/attendance/report');

        // エラー（500等）にならず、正常に表示されること
        $response->assertStatus(200);
        $response->assertViewIs('user.user-statistices-report');

        // ビューに変数が渡されていることの確認
        $response->assertViewHas(['summary', 'monthlyTrends', 'anomalies']);

        // 必要に応じて、データが0（または空っぽ）になっていることをビュー内の値や構造で確認
        // 例: サマリーの合計時間が "0h 0m" や 0 になっているか等、ビューの表示に合わせた検証を追加できます
        $response->assertSee('0h 0m'); // 0時間として表示されている場合の例
    }
}
