<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Admin;
use App\Models\AttendanceRecord;
use App\Models\AttendanceCorrection;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */
/**
     * テスト用ユーザーと勤怠レコードを作成する共通セットアップ
     */
    private function createTestUserAndAttendance()
    {
        $user = User::factory()->create();
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => Carbon::today()->format('Y-m-d'),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        return [$user, $attendance];
    }

    /**
     * テスト項目1: 出勤時間が退勤時間より後になっている場合、エラーメッセージが表示される
     */
    public function test_clock_in_after_clock_out_shows_error()
    {
        [$user, $attendance] = $this->createTestUserAndAttendance();

        // 1. 勤怠情報が登録されたユーザーにログインをする
        // 2. 勤怠詳細ページを開く (/attendance/detail/{id})
        // 3. 出勤時間を退勤時間より後に設定する
        // 4. 保存処理をする
        $response = $this->actingAs($user)->post('/attendance/detail/' . $attendance->id, [
            'new_clock_in'  => '19:00', // 退勤(18:00)より後
            'new_clock_out' => '18:00',
            'comment'       => 'テスト修正理由',
        ]);

        // バリデーションエラーメッセージがセッションに含まれていることを検証
        $response->assertSessionHasErrors([
            'new_clock_out' => '出勤時間もしくは退勤時間が不適切な値です',
        ]);
    }

    /**
     * テスト項目2: 休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示される
     */
    public function test_break_in_after_clock_out_shows_error()
    {
        [$user, $attendance] = $this->createTestUserAndAttendance();

        // 1. 勤怠情報が登録されたユーザーにログインをする
        // 2. 勤怠詳細ページを開く (/attendance/detail/{id})
        // 3. 休憩開始時間を退勤時間より後に設定する
        // 4. 保存処理をする
        $response = $this->actingAs($user)->post('/attendance/detail/' . $attendance->id, [
            'new_clock_in'  => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in'  => ['19:00'], // 退勤(18:00)より後
            'new_break_out' => ['19:30'],
            'comment'       => 'テスト修正理由',
        ]);

        // バリデーションエラーメッセージがセッションに含まれていることを検証
        $response->assertSessionHasErrors([
            'new_break_in.0' => '休憩時間が不適切な値です',
        ]);
    }

    /**
     * テスト項目3: 休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示される
     */
    public function test_break_out_after_clock_out_shows_error()
    {
        [$user, $attendance] = $this->createTestUserAndAttendance();

        // 1. 勤怠情報が登録されたユーザーにログインをする
        // 2. 勤怠詳細ページを開く (/attendance/detail/{id})
        // 3. 休憩終了時間を退勤時間より後に設定する
        // 4. 保存処理をする
        $response = $this->actingAs($user)->post('/attendance/detail/' . $attendance->id, [
            'new_clock_in'  => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in'  => ['12:00'],
            'new_break_out' => ['19:00'], // 退勤(18:00)より後
            'comment'       => 'テスト修正理由',
        ]);

        // バリデーションエラーメッセージがセッションに含まれていることを検証
        $response->assertSessionHasErrors([
            'new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です',
        ]);
    }

    /**
     * テスト項目4: 備考欄が未入力の場合のエラーメッセージが表示される
     */
    public function test_comment_required_shows_error()
    {
        [$user, $attendance] = $this->createTestUserAndAttendance();

        // 1. 勤怠情報が登録されたユーザーにログインをする
        // 2. 勤怠詳細ページを開く (/attendance/detail/{id})
        // 3. 備考欄を未入力のまま保存処理をする
        $response = $this->actingAs($user)->post('/attendance/detail/' . $attendance->id, [
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
     * テスト項目5: 修正申請処理が実行される
     */
    public function test_attendance_correction_process_executed()
    {
        [$user, $attendance] = $this->createTestUserAndAttendance();
        
        // 管理者もUserモデル（is_admin => true）で作成する
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        // 1. 勤怠情報が登録されたユーザーにログインをする
        // 2. 勤怠詳細を修正し保存処理をする (/attendance/detail/{id})
        $response = $this->actingAs($user)->post('/attendance/detail/' . $attendance->id, [
            'new_clock_in'    => '10:00',
            'new_clock_out'   => '19:00',
            'comment'         => '遅延のため修正申請',
            'approval_status' => '承認待ち',
        ]);

        // 保存処理によりデータベースに正しく申請が登録されていることを検証
        $this->assertDatabaseHas('attendance_corrections', [
            'attendance_record_id' => $attendance->id,
            'comment'              => '遅延のため修正申請',
            'approval_status'      => '承認待ち',
        ]);

        $correction = AttendanceCorrection::where('attendance_record_id', $attendance->id)->first();

        // 3. 管理者ユーザーで承認画面と申請一覧画面を確認する
        // 申請一覧画面 (/stamp_correction_request/list)
        $this->actingAs($admin, 'admin')
            ->get('/stamp_correction_request/list')
            ->assertStatus(200)
            ->assertSee('遅延のため修正申請');

        // 承認画面 (/stamp_correction_request/approve/{attendance_correct_request_id})
        $this->actingAs($admin, 'admin')
            ->get('/stamp_correction_request/approve/' . $correction->id)
            ->assertStatus(200);
    }

    /**
     * テスト項目6: 「承認待ち」にログインユーザーが行った申請が全て表示されていること
     */
    public function test_pending_applications_displayed_for_user()
    {
        [$user, $attendance] = $this->createTestUserAndAttendance();

        // 1. 勤怠情報が登録されたユーザーにログインをする
        // 2. 勤怠詳細を修正し保存処理をする
        $this->actingAs($user)->post('/attendance/detail/' . $attendance->id, [
            'new_clock_in'    => '10:00',
            'new_clock_out'   => '19:00',
            'comment'         => '承認待ち申請のテスト',
            'approval_status' => '承認待ち',
        ]);

        // 3. 申請一覧画面を確認する (/stamp_correction_request/list)
        $response = $this->actingAs($user)->get('/stamp_correction_request/list');

        $response->assertStatus(200);
        $response->assertSee('承認待ち');
        $response->assertSee('承認待ち申請のテスト');
    }

    /**
     * テスト項目7: 「承認済み」に管理者が承認した修正申請が全て表示されている
     */
    public function test_approved_applications_displayed_for_user()
    {
        [$user, $attendance] = $this->createTestUserAndAttendance();

        // 1. 勤怠情報が登録されたユーザーにログインをする
        // 2. 勤怠詳細を修正し保存処理をする（承認済みのデータを事前に用意してシミュレート）
        AttendanceCorrection::create([
            'attendance_record_id' => $attendance->id,
            'new_clock_in'         => '10:00:00',
            'new_clock_out'        => '19:00:00',
            'comment'              => '管理者承認済み申請データ',
            'approval_status'      => '承認済み',
        ]);

        // 3. 申請一覧画面を開く (/stamp_correction_request/list)
        // 4. 管理者が承認した修正申請が全て表示されていることを確認
        $response = $this->actingAs($user)->get('/stamp_correction_request/list');

        $response->assertStatus(200);
        $response->assertSee('承認済み');
        $response->assertSee('管理者承認済み申請データ');
    }

    /**
     * テスト項目8: 各申請の「詳細」を押下すると勤怠詳細画面に遷移する
     */
    public function test_detail_button_navigates_to_attendance_detail()
    {
        [$user, $attendance] = $this->createTestUserAndAttendance();

        // 1. 勤怠情報が登録されたユーザーにログインをする
        // 2. 勤怠詳細を修正し保存処理をする
        $this->actingAs($user)->post('/attendance/detail/' . $attendance->id, [
            'new_clock_in'    => '10:00',
            'new_clock_out'   => '19:00',
            'comment'         => '詳細遷移テスト',
            'approval_status' => '承認待ち',
        ]);

        // 3. 申請一覧画面を開く (/stamp_correction_request/list)
        $listResponse = $this->actingAs($user)->get('/stamp_correction_request/list');
        $listResponse->assertStatus(200);

        // 4. 「詳細」ボタンを押す（/attendance/detail/{id} にアクセスして正常にページが開くことを検証）
        $detailResponse = $this->actingAs($user)->get('/attendance/detail/' . $attendance->id);
        $detailResponse->assertStatus(200);
        $detailResponse->assertSee('勤怠詳細');
    }
}
