<?php

namespace Tests\Feature\Api;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceRecordTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */

    /**
     * ・GET /api/v1/attendance-records で勤怠一覧が JSON で取得できる
     */

    /** @test */
    public function can_get_attendance_records_list()
    {
        // 1. シーディングで勤怠データを作成（日付が被らないように sequence を使用）
        $user = User::factory()->create();

        AttendanceRecord::factory()
            ->count(3)
            ->state(new Sequence(
                ['date' => '2026-11-01'],
                ['date' => '2026-11-02'],
                ['date' => '2026-11-03'],
            ))
            ->for($user)
            ->create();

        Sanctum::actingAs($user, ['*']);

        // 2. GET /api/v1/attendance-records を実行
        $response = $this->getJson('/api/v1/attendance-records');

        // HTTP 200 が返り、レスポンスに data 配列と meta 情報が含まれる
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    /**
     * ・GET /api/v1/attendance-records/{attendanceRecord} で勤怠詳細が JSON で取得できる
     */
    /** @test */
    public function can_get_attendance_record_detail()
    {
        // 1. 勤怠データを作成
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->for($user)->create();

        Sanctum::actingAs($user, ['*']);

        // 2. GET /api/v1/attendance-records/{attendanceRecord} を実行
        $response = $this->getJson("/api/v1/attendance-records/{$record->id}");

        // HTTP 200 が返り、data に該当勤怠の詳細（ユーザー・休憩・修正申請含む）が含まれる
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'date',
                    'user',
                    'breaks',
                ],
            ]);
    }

    /**
     * ・存在しない ID では 404 とエラー JSON が返る
     */
    /** @test */
    public function returns_404_when_attendance_record_not_found_on_show()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        // 1. GET /api/v1/attendance-records/99999 を実行
        $response = $this->getJson('/api/v1/attendance-records/99999');

        // HTTP 404 が返り、レスポンスが { "error": "勤怠情報が見つかりませんでした。" }
        $response->assertStatus(404)
            ->assertJson([
                'error' => '勤怠情報が見つかりませんでした。',
            ]);
    }

    /**
     * ・POST /api/v1/attendance-records で勤怠が作成される
     */
    /** @test */
    public function can_create_attendance_record()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        // コントローラーのバリデーションで必須になっている項目（例: clock_in など）も追加する
        $data = [
            'date' => '2026-06-01',
            'clock_in' => '09:00:00', // 必要に応じて追加
            'clock_out' => '18:00:00', // 必要に応じて追加
        ];

        // 1. 正常なデータで POST /api/v1/attendance-records を実行
        $response = $this->postJson('/api/v1/attendance-records', $data);

        // HTTP 201 が返り、attendance_records テーブルにレコードが作成される
        $response->assertStatus(201);
        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'date' => '2026-06-01',
        ]);
    }

    /**
     * ・バリデーションエラー時に 422 と日本語エラーメッセージが返る
     */
    /** @test */
    public function returns_422_on_validation_error_when_creating()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        // 1. 不正なデータ（必須項目欠落）で POST /api/v1/attendance-records を実行
        $response = $this->postJson('/api/v1/attendance-records', []);

        // HTTP 422 が返り、errors フィールドに日本語のエラーメッセージが含まれる
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    /**
     * ・PUT /api/v1/attendance-records/{attendanceRecord} で勤怠が更新される
     * ・存在しない ID に対しては HTTP 404 を返す
     */
    /** @test */
    public function can_update_attendance_record_and_handle_404()
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->for($user)->create();

        Sanctum::actingAs($user, ['*']);

        $updateData = [
            'date' => '2026-06-02',
            'clock_in' => '10:00:00', // 必要に応じて追加
            'clock_out' => '19:00:00', // 必要に応じて追加
        ];

        // 1. 既存勤怠に対して PUT で更新データを送信（{attendanceRecord} 部分にIDを指定）
        $response = $this->putJson("/api/v1/attendance-records/{$record->id}", $updateData);

        // HTTP 200 が返り、レコードが更新されている
        $response->assertStatus(200);
        $this->assertDatabaseHas('attendance_records', [
            'id' => $record->id,
            'date' => '2026-06-02',
        ]);

        // 2. 存在しない ID に対して PUT を実行
        $nonExistentResponse = $this->putJson('/api/v1/attendance-records/99999', $updateData);

        // 存在しない ID に対しては HTTP 404 を返す
        $nonExistentResponse->assertStatus(404)
            ->assertJson([
                'error' => '勤怠情報が見つかりませんでした。',
            ]);
    }

    /**
     * ・DELETE /api/v1/attendance-records/{attendanceRecord} で勤怠が削除される
     * ・存在しない ID に対しては HTTP 404 を返す
     */
    /** @test */
    public function can_delete_attendance_record_and_handle_404()
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->for($user)->create();

        Sanctum::actingAs($user, ['*']);

        // 1. 既存勤怠に対して DELETE を送信（{attendanceRecord} 部分にIDを指定）
        $response = $this->deleteJson("/api/v1/attendance-records/{$record->id}");

        // HTTP 204 が返り、レコードが削除されている
        $response->assertStatus(204);
        $this->assertDatabaseMissing('attendance_records', [
            'id' => $record->id,
        ]);

        // 2. 存在しない ID に対して DELETE を実行
        $nonExistentResponse = $this->deleteJson('/api/v1/attendance-records/99999');

        // 存在しない ID に対しては HTTP 404 を返す
        $nonExistentResponse->assertStatus(404)
            ->assertJson([
                'error' => '勤怠情報が見つかりませんでした。',
            ]);
    }

    /**
     * ・未認証時に書き込み系 API で 401 が返る
     */
    /** @test */
    public function returns_401_when_unauthenticated_for_write_apis()
    {
        // 1. 先にユーザーを作成し、そのユーザーに紐づく勤怠レコードを作成する
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->for($user)->create();

        // 2. 認証なしで POST /api/v1/attendance-records を実行
        $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-12-31',
            'clock_in' => '09:00:00',
        ])
            ->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);

        $this->putJson("/api/v1/attendance-records/{$record->id}", [
            'date' => '2026-06-01',
        ])
            ->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);

        $this->deleteJson("/api/v1/attendance-records/{$record->id}")
            ->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    /**
     * ・認証済みユーザーは自分の勤怠を更新・削除できる
     */
    /** @test */
    public function authenticated_user_can_update_and_delete_own_record()
    {
        // 1. Sanctum::actingAs($user, ['*']) で認証
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->for($user)->create();

        Sanctum::actingAs($user, ['*']);

        // 2. 自分の勤怠に対して PUT/DELETE を実行
        // HTTP 200/204 が返り、操作が成功する
        $this->putJson("/api/v1/attendance-records/{$record->id}", ['date' => '2026-06-05'])
            ->assertStatus(200);

        $this->deleteJson("/api/v1/attendance-records/{$record->id}")
            ->assertStatus(204);
    }

    /**
     * ・他ユーザーの勤怠を更新・削除しようとすると 403 が返る
     */
    /** @test */
    public function returns_403_when_modifying_other_users_record()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $record = AttendanceRecord::factory()->for($owner)->create();

        // 1. Sanctum::actingAs($user, ['*']) で認証
        Sanctum::actingAs($otherUser, ['*']);

        // 2. 他ユーザーの勤怠に対して PUT/DELETE を実行
        // HTTP 403 が返り、レスポンスが { "error": "この操作を実行する権限がありません。" }
        $this->putJson("/api/v1/attendance-records/{$record->id}", ['date' => '2026-06-03'])
            ->assertStatus(403)
            ->assertJson(['error' => 'この操作を実行する権限がありません。']);

        $this->deleteJson("/api/v1/attendance-records/{$record->id}")
            ->assertStatus(403)
            ->assertJson(['error' => 'この操作を実行する権限がありません。']);
    }
}
