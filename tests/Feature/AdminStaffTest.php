<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStaffTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic feature test example.
     */
    /**
     * 管理者ユーザーが全一般ユーザーの「氏名」「メールアドレス」を確認できる
     * 1. 管理者でログインする
     * 2. スタッフ一覧ページを開く
     */
    public function test_admin_can_view_all_users_name_and_email_on_staff_list()
    {
        // 1. 管理者でログインする
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $staff1 = User::factory()->create([
            'is_admin' => false,
            'name' => 'テスト太郎',
            'email' => 'taro@example.com',
        ]);
        $staff2 = User::factory()->create([
            'is_admin' => false,
            'name' => 'テスト花子',
            'email' => 'hanako@example.com',
        ]);

        $this->actingAs($admin, 'admin');

        // 2. スタッフ一覧ページを開く
        $response = $this->get('/admin/staff/list');

        $response->assertStatus(200);
        $response->assertSee('テスト太郎');
        $response->assertSee('taro@example.com');
        $response->assertSee('テスト花子');
        $response->assertSee('hanako@example.com');
    }

    /**
     * ユーザーの勤怠情報が正しく表示される
     * 1. 管理者ユーザーでログインする
     * 2. 選択したユーザーの勤怠一覧ページを開く
     */
    public function test_admin_can_view_selected_user_attendance_records()
    {
        // 1. 管理者ユーザーでログインする
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $staff = User::factory()->create([
            'is_admin' => false,
            'name' => '勤怠確認スタッフ',
        ]);

        $today = Carbon::today();
        AttendanceRecord::create([
            'user_id' => $staff->id,
            'date' => $today->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $this->actingAs($admin, 'admin');

        // 2. 選択したユーザーの勤怠一覧ページを開く
        $response = $this->get('/admin/attendance/staff/'.$staff->id);

        $response->assertStatus(200);
        $response->assertSee($today->format('m月d日'));
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    /**
     * 「前月」を押下した時に表示月の前月の情報が表示される
     * 1. 管理者ユーザーにログインをする
     * 2. 勤怠一覧ページを開く
     * 3. 「前月」ボタンを押す
     */
    public function test_admin_can_view_previous_month_attendance()
    {
        // 1. 管理者ユーザーにログインをする
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $staff = User::factory()->create([
            'is_admin' => false,
        ]);

        $previousMonth = Carbon::today()->subMonth();

        AttendanceRecord::create([
            'user_id' => $staff->id,
            'date' => $previousMonth->copy()->startOfMonth()->toDateString(),
            'clock_in' => '08:00:00',
            'clock_out' => '17:00:00',
        ]);

        $this->actingAs($admin, 'admin');

        // 2. 勤怠一覧ページを開く（3. 「前月」ボタンを押す動作としてパラメータを付与）
        $response = $this->get('/admin/attendance/staff/'.$staff->id.'?date='.$previousMonth->format('Y-m'));

        $response->assertStatus(200);
        $response->assertSee($previousMonth->format('Y/m'));
        $response->assertSee('08:00');
        $response->assertSee('17:00');
    }

    /**
     * 「翌月」を押下した時に表示月の翌月の情報が表示される
     * 1. 管理者ユーザーにログインをする
     * 2. 勤怠一覧ページを開く
     * 3. 「翌月」ボタンを押す
     */
    public function test_admin_can_view_next_month_attendance()
    {
        // 1. 管理者ユーザーにログインをする
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $staff = User::factory()->create([
            'is_admin' => false,
        ]);

        $nextMonth = Carbon::today()->addMonth();

        AttendanceRecord::create([
            'user_id' => $staff->id,
            'date' => $nextMonth->copy()->startOfMonth()->toDateString(),
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
        ]);

        $this->actingAs($admin, 'admin');

        // 2. 勤怠一覧ページを開く（3. 「翌月」ボタンを押す動作としてパラメータを付与）
        $response = $this->get('/admin/attendance/staff/'.$staff->id.'?date='.$nextMonth->format('Y-m'));

        $response->assertStatus(200);
        $response->assertSee($nextMonth->format('Y/m'));
        $response->assertSee('10:00');
        $response->assertSee('19:00');
    }

    /**
     * 「詳細」を押下すると、その日の勤怠詳細画面に遷移する
     * 1. 管理者ユーザーにログインをする
     * 2. 勤怠一覧ページを開く
     * 3. 「詳細」ボタンを押下する
     */
    public function test_admin_can_navigate_to_attendance_detail_from_staff_attendance()
    {
        // 1. 管理者ユーザーにログインをする
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $staff = User::factory()->create([
            'is_admin' => false,
        ]);

        $attendance = AttendanceRecord::create([
            'user_id' => $staff->id,
            'date' => Carbon::today()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $this->actingAs($admin, 'admin');

        // 2. 勤怠一覧ページを開く
        $response = $this->get('/admin/attendance/staff/'.$staff->id);
        $response->assertStatus(200);

        // 3. 「詳細」ボタンを押下する（詳細画面へ遷移）
        $detailResponse = $this->get('/admin/attendance/'.$attendance->id);
        $detailResponse->assertStatus(200);
        $detailResponse->assertSee('09:00');
        $detailResponse->assertSee('18:00');
    }
}
