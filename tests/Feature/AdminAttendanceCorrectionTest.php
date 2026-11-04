<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AttendanceRecord;
use App\Models\AttendanceCorrection;
use App\Models\BreakTime;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AdminAttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */
    /**
    
    * テスト用ユーザー（管理者・一般）と勤怠レコードを作成する共通セットアップ
     */
    private function createTestTargetData()
    {
        // is_adminカラムがtrueの管理者ユーザーを作成
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        
        $user = User::factory()->create([
            'is_admin' => false, // 一般ユーザー
        ]);
        
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => Carbon::today()->format('Y-m-d'),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        return [$admin, $user, $attendance];
    }

    /**
     * テスト項目1: 勤怠詳細画面に表示されるデータが選択したものになっている
     */
    public function test_admin_attendance_detail_displays_selected_record_data()
    {
        [$admin, $user, $attendance] = $this->createTestTargetData();

        // 1. 管理者ユーザーにログインをする（adminガード）
        // 2. 勤怠詳細ページを開く (admin/attendance/{id})
        $response = $this->actingAs($admin, 'admin')->get('admin/attendance/' . $attendance->id);

        // 3. 画面が正常に表示され、選択したレコードのデータが反映されていることを確認
        $response->assertStatus(200);
        $response->assertSee('09:00'); // 出勤時間
        $response->assertSee('18:00'); // 退勤時間
    }

    /**
     * テスト項目2: 出勤時間が退勤時間より後になっている場合、エラーメッセージが表示される
     */
    public function test_admin_clock_in_after_clock_out_shows_error()
    {
        [$admin, $user, $attendance] = $this->createTestTargetData();

        // 1. 管理者ユーザーにログインをする（adminガード）
        // 2. 勤怠詳細ページを開く (admin/attendance/{id})
        // 3. 出勤時間を退勤時間より後に設定する
        // 4. 保存処理をする
        $response = $this->actingAs($admin, 'admin')->post('admin/attendance/' . $attendance->id, [
            'new_clock_in'  => '19:00', // 退勤(18:00)より後
            'new_clock_out' => '18:00',
            'comment'       => '管理者修正テスト理由',
        ]);

        // バリデーションエラーメッセージがセッションに含まれていることを検証
        $response->assertSessionHasErrors([
            'new_clock_out' => '出勤時間もしくは退勤時間が不適切な値です',
        ]);
    }

    /**
     * テスト項目3: 休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示される
     */
    public function test_admin_break_in_after_clock_out_shows_error()
    {
        [$admin, $user, $attendance] = $this->createTestTargetData();

        // 1. 管理者ユーザーにログインをする（adminガード）
        // 2. 勤怠詳細ページを開く (admin/attendance/{id})
        // 3. 休憩開始時間を退勤時間より後に設定する
        // 4. 保存処理をする
        $response = $this->actingAs($admin, 'admin')->post('admin/attendance/' . $attendance->id, [
            'new_clock_in'  => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in'  => ['19:00'], // 退勤(18:00)より後
            'new_break_out' => ['19:30'],
            'comment'       => '管理者修正テスト理由',
        ]);

        // バリデーションエラーメッセージがセッションに含まれていることを検証
        $response->assertSessionHasErrors([
            'new_break_in.0' => '休憩時間が不適切な値です',
        ]);
    }

    /**
     * テスト項目4: 休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示される
     */
    public function test_admin_break_out_after_clock_out_shows_error()
    {
        [$admin, $user, $attendance] = $this->createTestTargetData();

        // 1. 管理者ユーザーにログインをする（adminガード）
        // 2. 勤怠詳細ページを開く (admin/attendance/{id})
        // 3. 休憩終了時間を退勤時間より後に設定する
        // 4. 保存処理をする
        $response = $this->actingAs($admin, 'admin')->post('admin/attendance/' . $attendance->id, [
            'new_clock_in'  => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in'  => ['12:00'],
            'new_break_out' => ['19:00'], // 退勤(18:00)より後
            'comment'       => '管理者修正テスト理由',
        ]);

        // バリデーションエラーメッセージがセッションに含まれていることを検証
        $response->assertSessionHasErrors([
            'new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です',
        ]);
    }

    /**
     * テスト項目5: 備考欄が未入力の場合のエラーメッセージが表示される
     */
    public function test_admin_comment_required_shows_error()
    {
        [$admin, $user, $attendance] = $this->createTestTargetData();

        // 1. 管理者ユーザーにログインをする（adminガード）
        // 2. 勤怠詳細ページを開く (admin/attendance/{id})
        // 3. 備考欄を未入力のまま保存処理をする
        $response = $this->actingAs($admin, 'admin')->post('admin/attendance/' . $attendance->id, [
            'new_clock_in'  => '09:00',
            'new_clock_out' => '18:00',
            'comment'       => '', // 未入力
        ]);

        // バリデーションエラーメッセージがセッションに含まれていることを検証
        $response->assertSessionHasErrors([
            'comment' => '備考を記入してください',
        ]);
    }

    /**
     * テスト項目6: 承認待ちの修正申請が全て表示されている
     */
    public function test_admin_can_view_pending_corrections_list()
    {
        [$admin, $user, $attendance] = $this->createTestTargetData();

        AttendanceCorrection::create([
            'attendance_record_id' => $attendance->id,
            'new_clock_in'         => '10:00:00',
            'new_clock_out'        => '19:00:00',
            'comment'              => '承認待ちのテスト理由',
            'approval_status'      => '承認待ち',
        ]);

        // 1. 管理者ユーザーにログインをする
        // 2. 修正申請一覧ページを開き、承認待ちのタブを開く
        $response = $this->actingAs($admin, 'admin')->get('/stamp_correction_request/list');

        $response->assertStatus(200);
        $response->assertSee('承認待ちのテスト理由');
    }

    /**
     * テスト項目7: 承認済みの修正申請が全て表示されている
     */
    public function test_admin_can_view_approved_corrections_list()
    {
        [$admin, $user, $attendance] = $this->createTestTargetData();

        AttendanceCorrection::create([
            'attendance_record_id' => $attendance->id,
            'new_clock_in'         => '10:00:00',
            'new_clock_out'        => '19:00:00',
            'comment'              => '承認済みのテスト理由',
            'approval_status'      => '承認済み',
        ]);

        // 1. 管理者ユーザーにログインをする
        // 2. 修正申請一覧ページを開き、承認済みのタブを開く
        $response = $this->actingAs($admin, 'admin')->get('/stamp_correction_request/list');

        $response->assertStatus(200);
        $response->assertSee('承認済みのテスト理由');
    }

    /**
     * テスト項目8: 修正申請の詳細内容が正しく表示されている
     */
    public function test_admin_can_view_correction_detail()
    {
        [$admin, $user, $attendance] = $this->createTestTargetData();

        $correction = AttendanceCorrection::create([
            'attendance_record_id' => $attendance->id,
            'new_clock_in'         => '10:00:00',
            'new_clock_out'        => '19:00:00',
            'comment'              => '詳細確認用の理由',
            'approval_status'      => '承認待ち',
        ]);

        // 1. 管理者ユーザーにログインをする
        // 2. 修正申請の詳細画面を開く (/stamp_correction_request/approve/{id})
        $response = $this->actingAs($admin, 'admin')->get('/stamp_correction_request/approve/' . $correction->id);

        $response->assertStatus(200);
        $response->assertSee('詳細確認用の理由');
        $response->assertSee('10:00');
        $response->assertSee('19:00');
        // 承認待ち状態なので「承認」ボタンが表示されていることを確認
        $response->assertSee('承認');
    }

    /**
     * テスト項目9: 修正申請の承認処理が正しく行われる（元テーブルへの反映、ステータス変更、承認済み画面の制御）
     */
    public function test_admin_can_approve_pending_correction()
    {
        [$admin, $user, $attendance] = $this->createTestTargetData();

        $correction = AttendanceCorrection::create([
            'attendance_record_id' => $attendance->id,
            'new_clock_in'         => '10:00:00',
            'new_clock_out'        => '19:00:00',
            'comment'              => '承認処理テスト',
            'approval_status'      => '承認待ち',
        ]);

        // 1. 管理者ユーザーにログインをする
        // 2. 修正申請の詳細画面で「承認」ボタンを押す（POSTリクエスト：パラメータ不要でIDのみで処理）
        $response = $this->actingAs($admin, 'admin')->post('/stamp_correction_request/approve/' . $correction->id);

        // 3. データベースの検証
        // ① 修正申請のステータスが「承認済み」に変更されていること
        $this->assertDatabaseHas('attendance_corrections', [
            'id' => $correction->id,
            'approval_status' => '承認済み',
        ]);

        // ② 元の勤怠テーブル（attendance_records）に出退勤時間が上書きされていること
        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
        ]);

        // 4. 承認済みの詳細画面に再度アクセスした際、「承認済み」と表示され「承認」ボタンが表示されないことの確認
        $approvedDetailResponse = $this->actingAs($admin, 'admin')->get('/stamp_correction_request/approve/' . $correction->id);
        $approvedDetailResponse->assertStatus(200);
        $approvedDetailResponse->assertSee('承認済み');
        $approvedDetailResponse->assertDontSee('class="applied-form__button--submit"'); // 承認ボタンが消えていること
    }
}
