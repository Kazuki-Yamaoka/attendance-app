<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AttendanceListAndCorrectionTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */

    /** @test */
    public function 自分が行った勤怠情報が全て表示されている()
    {
        // 1. ユーザーを作成してログインする
        $user = User::factory()->create();

        // ★ 今月の中で確実に収まる日付を、Carbonオブジェクトのまま変数で用意する
        $baseDate = now()->startOfMonth(); // 今月の1日
        $date1 = $baseDate->copy()->addDays(1); // 2日
        $date2 = $baseDate->copy()->addDays(2); // 3日
        $date3 = $baseDate->copy()->addDays(3); // 4日

        // データベース保存用は toDateString()（Y-m-d）を使う
        $attendance1 = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $date1->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $attendance2 = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $date2->toDateString(),
            'clock_in' => '08:30:00',
            'clock_out' => '17:30:00',
        ]);

        $attendance3 = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $date3->toDateString(),
            'clock_in' => '09:15:00',
            'clock_out' => '19:00:00',
        ]);

        // 2. 勤怠一覧ページを開く
        $response = $this->actingAs($user)->get('/attendance/list');

        // 3. すべての勤怠情報が一覧画面に表示されていることを確認する
        $response->assertStatus(200);
        $response->assertSee(now()->format('Y/m'));

        // ★ アサート側は、同じCarbonオブジェクトから format('Y/m/d') で呼び出す
        $response->assertSee($date1->format('Y/m/d'));
        $response->assertSee(Carbon::parse($attendance1->clock_in)->format('H:i'));
        $response->assertSee(Carbon::parse($attendance1->clock_out)->format('H:i'));
        
        $response->assertSee($date2->format('Y/m/d'));
        $response->assertSee(Carbon::parse($attendance2->clock_in)->format('H:i'));
        $response->assertSee(Carbon::parse($attendance2->clock_out)->format('H:i'));

        $response->assertSee($date3->format('Y/m/d'));
        $response->assertSee(Carbon::parse($attendance3->clock_in)->format('H:i'));
        $response->assertSee(Carbon::parse($attendance3->clock_out)->format('H:i'));
    }

    /** @test */
    public function 勤怠一覧画面に遷移した際に現在の月が表示される()
    {
        // 1. ユーザーにログインをする
        $user = User::factory()->create();

        // 2. 勤怠一覧ページを開く
        $response = $this->actingAs($user)->get('/attendance/list');

        // 3. 現在の月が表示されていることを確認
        $response->assertStatus(200);
        $response->assertSee(now()->format('Y/m'));
    }

    /** @test */
    public function 前月を押下した時に表示月の前月の情報が表示される()
    {
        // 1. 勤怠情報が登録されたユーザーにログインをする
        $user = User::factory()->create();
        
        // 前月のデータを複数件作成
        $previousMonth = now()->subMonth();
        $attendance1 = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $previousMonth->copy()->startOfMonth()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
        $attendance2 = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $previousMonth->copy()->startOfMonth()->addDays(5)->toDateString(),
            'clock_in' => '08:30:00',
            'clock_out' => '17:30:00',
        ]);

        // 2. 勤怠一覧ページを開く
        $this->actingAs($user)->get('/attendance/list');

        // 3. 「前月」ボタンを押下する（URLに前月の年月パラメータを渡す）
        $response = $this->actingAs($user)->get('/attendance/list?date=' . $previousMonth->format('Y-m'));

        // 前月の月表示および複数の勤怠情報が表示されていることを確認
        $response->assertStatus(200);
        $response->assertSee($previousMonth->format('Y/m'));

        $response->assertSee(Carbon::parse($attendance1->date)->format('Y/m/d'));
        $response->assertSee(Carbon::parse($attendance1->clock_in)->format('H:i'));
        $response->assertSee(Carbon::parse($attendance1->clock_out)->format('H:i'));
        
        $response->assertSee(Carbon::parse($attendance2->date)->format('Y/m/d'));
        $response->assertSee(Carbon::parse($attendance2->clock_in)->format('H:i'));
        $response->assertSee(Carbon::parse($attendance2->clock_out)->format('H:i'));
    }

    /** @test */
    public function 翌月を押下した時に表示月の翌月の情報が表示される()
    {
        // 1. 勤怠情報が登録されたユーザーにログインをする
        $user = User::factory()->create();

        // 翌月のデータを複数件作成
        $nextMonth = now()->addMonth();
        $attendance1 = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $nextMonth->copy()->startOfMonth()->toDateString(),
            'clock_in' => '09:00',
            'clock_out' => '18:00',
        ]);
        $attendance2 = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $nextMonth->copy()->startOfMonth()->addDays(5)->toDateString(),
            'clock_in' => '09:30',
            'clock_out' => '18:30',
        ]);

        // 2. 勤怠一覧ページを開く
        $this->actingAs($user)->get('/attendance/list');

        // 3. 「翌月」ボタンを押下する（URLに翌月の年月パラメータを渡す）
        $response = $this->actingAs($user)->get('/attendance/list?date=' . $nextMonth->format('Y-m'));

        // 翌月の月表示および複数の勤怠情報が表示されていることを確認
        $response->assertStatus(200);
        $response->assertSee($nextMonth->format('Y/m'));
        
        $response->assertSee(Carbon::parse($attendance1->date)->format('Y/m/d'));
        $response->assertSee(Carbon::parse($attendance1->clock_in)->format('H:i'));
        $response->assertSee(Carbon::parse($attendance1->clock_out)->format('H:i'));
        
        $response->assertSee(Carbon::parse($attendance2->date)->format('Y/m/d'));
        $response->assertSee(Carbon::parse($attendance2->clock_in)->format('H:i'));
        $response->assertSee(Carbon::parse($attendance2->clock_out)->format('H:i'));
    }

    /** @test */
    public function 詳細を押下するとその日の勤怠詳細画面に遷移する()
    {
        // 1. 勤怠情報が登録されたユーザーにログインをする
        $user = User::factory()->create();
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 2. 勤怠一覧ページを開く
        $response = $this->actingAs($user)->get('/attendance/list');
        $response->assertStatus(200);

        // 3. 「詳細」ボタンを押下する
        $response = $this->actingAs($user)->get('/attendance/detail/' . $attendance->id);

        // 該当する日の勤怠詳細画面に正常に遷移することを確認
        $response->assertStatus(200);
    }

    /** @test */
    public function 勤怠詳細画面の名前がログインユーザーの氏名になっている()
    {
        // 1. 勤怠情報が登録されたユーザーにログインをする
        $user = User::factory()->create([
            'name' => 'テスト 太郎', // 任意のテスト用氏名
        ]);
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 2. 勤怠詳細ページを開く（/attendance/detail/{id}）
        $response = $this->actingAs($user)->get('/attendance/detail/' . $attendance->id);

        // 3. 名前欄にログインユーザーの氏名が表示されていることを確認する
        $response->assertStatus(200);
        $response->assertSee('テスト 太郎');
    }

    /** @test */
    public function 勤怠詳細画面の日付が選択した日付になっている()
    {
        // 1. 勤怠情報が登録されたユーザーにログインをする
        $user = User::factory()->create();
        
        // データベースには date型に合わせた標準フォーマット（Y-m-d）で保存する
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-15', 
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 2. 勤怠詳細ページを開く
        $response = $this->actingAs($user)->get('/attendance/detail/' . $attendance->id);

        // 3. 勤怠詳細画面のステータスと、画面に表示されている日付の文字列を確認する
        $response->assertStatus(200);
        
        // コントローラー側で Y年 と n月j日 に変換されてビューに渡されているため、
        // 画面上にそれぞれの文字列が存在するかを assertSee で確認する
        $response->assertSee('2026年');
        $response->assertSee('10月15日');
    }

    /** @test */
    public function 出勤退勤にて記されている時間がログインユーザーの打刻と一致している()
    {
        // 1. 勤怠情報が登録されたユーザーにログインをする
        $user = User::factory()->create();
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 2. 勤怠詳細ページを開く
        $response = $this->actingAs($user)->get('/attendance/detail/' . $attendance->id);

        // 3. 出勤・退勤欄に記録された時間が表示されていることを確認する
        $response->assertStatus(200);
        $response->assertSee('09:00'); // コントローラー側でフォーマットされた時間に合わせ適宜調整してください
        $response->assertSee('18:00');
    }

    /** @test */
    public function 休憩にて記されている時間がログインユーザーの打刻と一致している()
    {
        // 1. 勤怠情報が登録されたユーザーにログインをする
        $user = User::factory()->create();
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 休憩データを作成
        BreakTime::create([
            'attendance_record_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        // 2. 勤怠詳細ページを開く
        $response = $this->actingAs($user)->get('/attendance/detail/' . $attendance->id);

        // 3. 休憩欄に記録された時間（休憩入・休憩戻）が表示されていることを確認する
        $response->assertStatus(200);
        $response->assertSee('12:00');
        $response->assertSee('13:00');
    }
}
