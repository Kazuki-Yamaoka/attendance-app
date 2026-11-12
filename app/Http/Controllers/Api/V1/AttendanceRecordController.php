<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Requests\Api\V1\StoreAttendanceRecordRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttendanceRecordController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    /**
     * 勤怠一覧取得API
     */
    public function index(IndexAttendanceRecordRequest $request): AnonymousResourceCollection
    {
        // 1. 関連データを Eager Load（N+1問題の防止）
        $query = AttendanceRecord::with(['user', 'breaks', 'applications']);

        // 2. when() を使った条件付きフィルタリング
        $query->when($request->input('user_id'), function ($q, $userId) {
            $q->where('user_id', $userId);
        })
            ->when($request->input('date'), function ($q, $date) {
                $q->whereDate('date', $date);
            })
            ->when($request->input('month'), function ($q, $month) {
                $q->whereYear('date', '=', date('Y', strtotime($month)))
                    ->whereMonth('date', '=', date('m', strtotime($month)));
            });

        // 3. ページネーションの件数取得
        $perPage = $request->input('per_page', 20);

        // 4. 日付の降順でページネーション実行
        $attendanceRecord_records = $query->latest('date')->paginate($perPage);

        // 5. 各レコードに計算した勤務時間・休憩時間を付与
        $attendanceRecord_records->getCollection()->transform(function ($record) {
            $record->calculated_total_time = $this->calculateTotalTime($record);
            $record->calculated_total_break_time = $this->calculateTotalBreakTime($record);

            return $record;
        });

        // 6. リソースコレクションに包んで返却
        return AttendanceRecordResource::collection($attendanceRecord_records);
    }

    /**
     * 勤怠詳細取得API
     */
    public function show(string $id): AttendanceRecordResource|JsonResponse
    {
        // 1. まずIDでモデルを取得（見つからない場合はカスタムJSONを返す）
        $attendanceRecord = AttendanceRecord::find($id);

        if (! $attendanceRecord) {
            return response()->json([
                'error' => '勤怠情報が見つかりませんでした。',
            ], 404);
        }

        // 2. 関連データをロード（※ 詳細で必要なリレーションだけにするのもスッキリします）
        $attendanceRecord->load(['user', 'breaks', 'applications']);

        // 3. リソースでラップして返却（※計算プロパティの付与は不要なので削除）
        return new AttendanceRecordResource($attendanceRecord);
    }

    /**
     * 総勤務時間を計算 (出勤〜退勤 - 総休憩時間)
     */
    private function calculateTotalTime(AttendanceRecord $record): ?string
    {
        if (! $record->clock_in || ! $record->clock_out) {
            return null;
        }

        $clockIn = Carbon::parse($record->clock_in);
        $clockOut = Carbon::parse($record->clock_out);
        $workMinutes = $clockIn->diffInMinutes($clockOut);

        // 休憩時間を合算して引く
        $breakMinutes = 0;
        foreach ($record->breaks as $break) {
            if ($break->break_in && $break->break_out) {
                $bIn = Carbon::parse($break->break_in);
                $bOut = Carbon::parse($break->break_out);
                $breakMinutes += $bIn->diffInMinutes($bOut);
            }
        }

        $netMinutes = max(0, $workMinutes - $breakMinutes);
        $hours = floor($netMinutes / 60);
        $minutes = $netMinutes % 60;

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    /**
     * 総休憩時間を計算
     */
    private function calculateTotalBreakTime(AttendanceRecord $record): ?string
    {
        $breakMinutes = 0;
        foreach ($record->breaks as $break) {
            if ($break->break_in && $break->break_out) {
                $bIn = Carbon::parse($break->break_in);
                $bOut = Carbon::parse($break->break_out);
                $breakMinutes += $bIn->diffInMinutes($bOut);
            }
        }

        if ($breakMinutes === 0) {
            return '00:00';
        }

        $hours = floor($breakMinutes / 60);
        $minutes = $breakMinutes % 60;

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    public function store(StoreAttendanceRecordRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        // datetime 型のカラムに合わせて日付と時間を結合する場合
        if (isset($validatedData['clock_in'])) {
            $validatedData['clock_in'] = $validatedData['date'].' '.$validatedData['clock_in'];
        }

        if (isset($validatedData['clock_out'])) {
            $validatedData['clock_out'] = $validatedData['date'].' '.$validatedData['clock_out'];
        }

        // 1. 勤怠レコードを作成
        $attendanceRecord = $request->user()->attendanceRecords()->create($validatedData);

        // 2. 関連データをEager Loadする
        $attendanceRecord->load(['user', 'breaks', 'applications']);

        // 3. 勤務時間・休憩時間を計算してプロパティに持たせる（index表示用）
        $attendanceRecord->calculated_total_time = $this->calculateTotalTime($attendanceRecord);
        $attendanceRecord->calculated_total_break_time = $this->calculateTotalBreakTime($attendanceRecord);

        // 4. Resourceでラップし、ステータスコード201でレスポンスを返す
        return (new AttendanceRecordResource($attendanceRecord))
            ->response()
            ->setStatusCode(201);
    }

    // 引数を $id から AttendanceRecord $attendanceRecord に変更する
    public function update(UpdateAttendanceRecordRequest $request, AttendanceRecord $attendanceRecord): JsonResponse
    {
        // ルートモデル結合を使っていれば、存在しないIDの場合は自動的に
        // 404（ModelNotFoundException）がスローされます。
        // （以前 Handler.php で api/* のときは 404 JSON を返す設定にしたはずなので、自動で意図したJSONになります）

        // 2. ポリシーによる認可チェック
        try {
            $this->authorize('update', $attendanceRecord);
        } catch (AuthorizationException $e) {
            return response()->json([
                'error' => 'この操作を実行する権限がありません。',
            ], 403);
        }

        // バリデーション済みデータを取得
        $validatedData = $request->validated();

        // 3. 日付と時刻の結合処理...
        $targetDate = $validatedData['date'] ?? $attendanceRecord->date;

        if (isset($validatedData['clock_in'])) {
            $validatedData['clock_in'] = $targetDate.' '.$validatedData['clock_in'];
        }

        if (isset($validatedData['clock_out'])) {
            $validatedData['clock_out'] = $targetDate.' '.$validatedData['clock_out'];
        }

        // 4. レコードの更新
        $attendanceRecord->update($validatedData);

        // 5. リレーションのロード
        $attendanceRecord->load(['user', 'breaks']);

        // 6. レスポンス
        return (new AttendanceRecordResource($attendanceRecord))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        // 1. 指定されたIDの勤怠データを取得（存在しない場合は404とカスタムJSONを返す）
        $attendanceRecord = AttendanceRecord::find($id);

        if (! $attendanceRecord) {
            return response()->json([
                'error' => '勤怠情報が見つかりませんでした。',
            ], 404);
        }

        // 2. ポリシーによる認可チェック（他ユーザーの削除などを防ぐ）
        try {
            $this->authorize('delete', $attendanceRecord);
        } catch (AuthorizationException $e) {
            return response()->json([
                'error' => 'この操作を実行する権限がありません。',
            ], 403);
        }

        // 3. レコードの削除
        $attendanceRecord->delete();

        // 4. 成功時（204 No Content）はボディなしでレスポンスを返す
        return response()->json(null, 204);
    }
}
