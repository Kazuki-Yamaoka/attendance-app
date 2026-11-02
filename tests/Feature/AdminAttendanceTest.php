<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AdminAttendanceTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */
/**
     * 1. その日になされた全ユーザーの勤怠情報が正確に確認できること
     */
    public function test_admin_can_see_all_users_attendance_records_accurately()
    {
        // 管理者ユーザーを作成し、'admin' ガードでログイン
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        // 複数人のスタッフと本日の勤怠データを作成
        $staff1 = User::factory()->create([
            'is_admin' => false,
            'name' => 'スタッフ一郎',
        ]);
        $staff2 = User::factory()->create([
            'is_admin' => false,
            'name' => 'スタッフ二郎',
        ]);

        $today = Carbon::today()->toDateString();

        AttendanceRecord::create([
            'user_id' => $staff1->id,
            'date' => $today,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        AttendanceRecord::create([
            'user_id' => $staff2->id,
            'date' => $today,
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
        ]);

        $this->actingAs($admin, 'admin');

        // 勤怠一覧画面を開く
        $response = $this->get('/admin/attendance/list');

        $response->assertStatus(200);

        // 全ユーザーの勤怠情報（名前・出勤時間・退勤時間）が正確に表示されていることを確認
        $response->assertSee('スタッフ一郎');
        $response->assertSee('09:00');
        $response->assertSee('18:00');

        $response->assertSee('スタッフ二郎');
        $response->assertSee('10:00');
        $response->assertSee('19:00');
    }

    /**
     * 2. 遷移した際に現在の日付が表示されること
     */
    public function test_admin_sees_current_date_on_attendance_list()
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->get('/admin/attendance/list');

        $response->assertStatus(200);

        // 遷移した際に現在の日付が表示されていることを確認
        $todayFormatted = Carbon::today()->format('Y/m/d');
        $response->assertSee($todayFormatted);
    }

    /**
     * 3. 「前日」ボタンを押下した時に前の日の勤怠情報が表示されること
     */
    public function test_admin_can_view_previous_day_attendance_and_records()
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $staff = User::factory()->create([
            'is_admin' => false,
            'name' => '前日スタッフ',
        ]);

        $previousDate = Carbon::yesterday();
        $previousDateFormatted = $previousDate->format('Y/m/d');

        // 前日の勤怠データを作成
        AttendanceRecord::create([
            'user_id' => $staff->id,
            'date' => $previousDate->toDateString(),
            'clock_in' => '08:30:00',
            'clock_out' => '17:30:00',
        ]);

        $this->actingAs($admin, 'admin');

        // 前日のパラメータを指定してアクセス
        $response = $this->get('/admin/attendance/list?date=' . $previousDate->toDateString());

        $response->assertStatus(200);
        // 前日の日付と、その日のスタッフの勤怠情報が表示されていることを確認
        $response->assertSee($previousDateFormatted);
        $response->assertSee('前日スタッフ');
        $response->assertSee('08:30');
        $response->assertSee('17:30');
    }

    /**
     * 4. 「翌日」ボタンを押下した時に次の日の勤怠情報が表示されること
     */
    public function test_admin_can_view_next_day_attendance_and_records()
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $staff = User::factory()->create([
            'is_admin' => false,
            'name' => '翌日スタッフ',
        ]);

        $nextDate = Carbon::tomorrow();
        $nextDateFormatted = $nextDate->format('Y/m/d');

        // 翌日の勤怠データを作成
        AttendanceRecord::create([
            'user_id' => $staff->id,
            'date' => $nextDate->toDateString(),
            'clock_in' => '09:30:00',
            'clock_out' => '18:30:00',
        ]);

        $this->actingAs($admin, 'admin');

        // 翌日のパラメータを指定してアクセス
        $response = $this->get('/admin/attendance/list?date=' . $nextDate->toDateString());

        $response->assertStatus(200);
        // 翌日の日付と、その日のスタッフの勤怠情報が表示されていることを確認
        $response->assertSee($nextDateFormatted);
        $response->assertSee('翌日スタッフ');
        $response->assertSee('09:30');
        $response->assertSee('18:30');
    }
}
