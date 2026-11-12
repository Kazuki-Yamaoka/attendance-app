<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */

    /** @test */
    public function 勤怠打刻画面に現在の日時情報が_u_iと同じ形式で表示されている()
    {
        // 1. 一般ユーザーを作成してログインする
        $user = User::factory()->create();

        // 2. 勤怠打刻画面を開く (/attendance)
        $response = $this->actingAs($user)->get('/attendance');

        // 3. 画面が正常に表示されることを確認
        $response->assertStatus(200);

        // 4. コントローラー側と同じ形式（translatedFormat）で日付が表示されていることを確認する
        $expectedDate = Carbon::now()->translatedFormat('Y年m月d日（D）');
        $response->assertSee($expectedDate);
    }

    /** @test */
    public function 勤務外の場合、勤怠ステータスが正しく表示される()
    {
        // 1. ステータスが勤務外のユーザーにログインする（本日の打刻データなし）
        $user = User::factory()->create();

        // 2. 勤怠打刻画面を開く
        $response = $this->actingAs($user)->get('/attendance');

        // 3. 画面に表示されているステータスを確認する
        $response->assertStatus(200);
        $response->assertSee('勤務外');
    }

    /** @test */
    public function 出勤中の場合、勤怠ステータスが正しく表示される()
    {
        // 1. ステータスが出勤中のユーザーにログインする（clock_in〇、clock_out×、breakなし）
        $user = User::factory()->create();
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        // 2. 勤怠打刻画面を開く
        $response = $this->actingAs($user)->get('/attendance');

        // 3. 画面に表示されているステータスを確認する
        $response->assertStatus(200);
        $response->assertSee('出勤中');
    }

    /** @test */
    public function 休憩中の場合、勤怠ステータスが正しく表示される()
    {
        // 1. ステータスが休憩中のユーザーにログインする（clock_in〇、clock_out×、break_in〇、break_out×）
        $user = User::factory()->create();
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);
        BreakTime::create([
            'attendance_record_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => null,
        ]);

        // 2. 勤怠打刻画面を開く
        $response = $this->actingAs($user)->get('/attendance');

        // 3. 画面に表示されているステータスを確認する
        $response->assertStatus(200);
        $response->assertSee('休憩中');
    }

    /** @test */
    public function 退勤済の場合、勤怠ステータスが正しく表示される()
    {
        // 1. ステータスが退勤済のユーザーにログインする（clock_in〇、clock_out〇）
        $user = User::factory()->create();
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 2. 勤怠打刻画面を開く
        $response = $this->actingAs($user)->get('/attendance');

        // 3. 画面に表示されているステータスを確認する
        $response->assertStatus(200);
        $response->assertSee('退勤済');
    }

    /** @test */
    public function 出勤ボタンが正しく機能する()
    {
        // 1. ステータスが勤務外のユーザーにログインする
        $user = User::factory()->create();

        // 2. 画面に「出勤」ボタンが表示されていることを確認する
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('出勤');

        // 3. 出勤の処理を行う（ビューの name="action" value="clock_in" を送信）
        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'clock_in',
        ]);

        // redirect()->back() によるリダイレクト（302）が返ることを確認
        $response->assertStatus(302);

        // データベースに本日のレコードが存在することを確認
        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'date' => now()->toDateString(),
        ]);

        // clock_in カラムにちゃんと時間が格納されている（nullではない）ことを確認
        $attendance = AttendanceRecord::where('user_id', $user->id)
            ->where('date', now()->toDateString())
            ->first();

        $this->assertNotNull($attendance->clock_in);
    }

    /** @test */
    public function 出勤は一日一回のみできる()
    {
        // 1. ステータスが退勤済（または当日すでに出勤済み）であるユーザーにログインする
        $user = User::factory()->create();
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 2. 勤務ボタン（出勤ボタン）が表示されないことを確認する
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);

        // 「出勤」の文言（ボタン）が表示されていないことを確認
        $response->assertDontSee('出勤');
    }

    /** @test */
    public function 出勤時刻が勤怠一覧画面で確認できる()
    {
        // 1. ステータスが勤務外のユーザーにログインする
        $user = User::factory()->create();

        // 2. 出勤の処理を行う
        $this->actingAs($user)->post('/attendance', [
            'action' => 'clock_in',
        ]);

        // データベースに保存された打刻データを取得
        $attendance = AttendanceRecord::where('user_id', $user->id)
            ->whereNull('clock_out')
            ->latest('id')
            ->first();

        // 3. 勤怠一覧画面から日付と出勤時刻が確認できる（URL: /attendance/list）
        $response = $this->actingAs($user)->get('/attendance/list');

        $response->assertStatus(200);

        // 一覧画面に日付が表示されていることを確認
        $response->assertSee(now()->format('Y/m/d')); // ※実際の表示形式（Y/m/d など）に合わせて調整してください

        // データベースに記録された clock_in の時間（例: "09:15:30" → "09:15" など）が
        // 画面上に表示されていることを確認
        // ※ビューでの表示形式（H:i など）に合わせてフォーマットしてください
        $clockIn = Carbon::parse($attendance->clock_in)->format('H:i');
        $response->assertSee($clockIn);
    }

    /** @test */
    public function 休憩ボタンが正しく機能する()
    {
        // 1. ステータスが出勤中のユーザーにログインする
        $user = User::factory()->create();
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        // 2. 画面に「休憩入」ボタンが表示されていることを確認する
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩入');

        // 3. 休憩の処理を行う（POSTリクエストで action=break_in を送信）
        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'break_in',
        ]);
        $response->assertStatus(302); // redirect->back()

        // データベースの break_times テーブルに休憩開始時間が記録されていることを確認
        $attendance = AttendanceRecord::where('user_id', $user->id)->first();
        $this->assertDatabaseHas('break_times', [
            'attendance_record_id' => $attendance->id,
        ]);

        $breakTime = BreakTime::where('attendance_record_id', $attendance->id)->first();
        $this->assertNotNull($breakTime->break_in);
    }

    /** @test */
    public function 休憩は一日に何回でもできる()
    {
        // 1. ステータスが出勤中であるユーザーにログインする
        $user = User::factory()->create();
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        // 2. 休憩入と休憩戻の処理を行う（1回目の休憩サイクル完了）
        $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);

        // 休憩中のレコードを取得して break_out を記録（または休憩戻処理）
        $breakTime = BreakTime::where('attendance_record_id', $attendance->id)->latest()->first();
        $breakTime->update(['break_out' => '12:30:00']);

        // 3. 再び「休憩入」ボタンが表示されることを確認する
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩入');
    }

    /** @test */
    public function 休憩戻ボタンが正しく機能する()
    {
        // 1. ステータスが出勤中であるユーザーにログインする
        $user = User::factory()->create();
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        // 2. 休憩入の処理を行う
        $this->actingAs($user)->post('/attendance', [
            'action' => 'break_in',
        ]);

        // 3. 休憩戻の処理を行う（action=break_out を送信）
        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'break_out',
        ]);
        $response->assertStatus(302);

        // break_times テーブルの break_out に時間が記録されていることを確認
        $breakTime = BreakTime::where('attendance_record_id', $attendance->id)->first();
        $this->assertNotNull($breakTime->break_out);
    }

    /** @test */
    public function 休憩戻は一日に何回でもできる()
    {
        // 1. ステータスが出勤中であるユーザーにログインする
        $user = User::factory()->create();
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        // 2. 休憩入と休憩戻の処理を行い、再度休憩入の処理を行う
        $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);
        $this->actingAs($user)->post('/attendance', ['action' => 'break_out']);

        // 2回目の休憩入
        $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);

        // 3. 「休憩戻」ボタンが表示されることを確認する（現在休憩中のため）
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩戻');
    }

    /** @test */
    public function 休憩時刻が勤怠一覧画面で確認できる()
    {
        // 1. ステータスが勤務中のユーザーにログインする
        $user = User::factory()->create();
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        // 2. 休憩入と休憩戻の処理を行う
        $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);
        $this->actingAs($user)->post('/attendance', ['action' => 'break_out']);

        // 3. 勤怠一覧画面から休憩の日付を確認する（URL: /attendance/list）
        $response = $this->actingAs($user)->get('/attendance/list');

        $response->assertStatus(200);

        // ビューの表示形式（Y/m/d）に合わせて日付文字列に変換して確認する
        $formattedDate = Carbon::parse($attendance->date)->format('Y/m/d');
        $response->assertSee($formattedDate);
    }

    /** @test */
    public function 退勤ボタンが正しく機能する()
    {
        // 1. ステータスが勤務中のユーザーにログインする
        $user = User::factory()->create();
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        // 2. 画面に「退勤」ボタンが表示されていることを確認する
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('退勤');

        // 3. 退勤の処理を行う（POSTリクエストで action=clock_out を送信）
        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'clock_out',
        ]);
        $response->assertStatus(302); // redirect->back()

        // データベースの clock_out カラムに時間が記録されている（nullではない）ことを確認
        $attendance->refresh(); // 最新のデータを再取得
        $this->assertNotNull($attendance->clock_out);
    }

    /** @test */
    public function 退勤時刻が勤怠一覧画面で確認できる()
    {
        // 1. ステータスが勤務外のユーザーにログインする
        $user = User::factory()->create();

        // 2. 出勤と退勤の処理を行う
        $this->actingAs($user)->post('/attendance', ['action' => 'clock_in']);
        $this->actingAs($user)->post('/attendance', ['action' => 'clock_out']);

        // ユーザーの最新の勤怠レコードを確実に取得する
        $attendance = $user->attendanceRecords()->latest('id')->first();

        // 念のためレコードや退勤時刻が取れているかアサーションで確認しておくと安心
        $this->assertNotNull($attendance);
        $this->assertNotNull($attendance->clock_out);

        $clockOut = Carbon::parse($attendance->clock_out)->format('H:i');

        // 3. 勤怠一覧画面から退勤の時間（または日付）を確認する（URL: /attendance/list）
        $response = $this->actingAs($user)->get('/attendance/list');

        $response->assertStatus(200);

        // 一覧画面に日付と退勤時刻が表示されていることを確認
        $response->assertSee(now()->format('Y/m'));
        $response->assertSee($clockOut);
    }
}
