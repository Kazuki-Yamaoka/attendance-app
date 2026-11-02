<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\User;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminAttendanceController extends Controller
{
    /**
     * 管理者用 勤怠一覧画面（日ごと）
     */
    public function index(Request $request)
    {
        // 1. リクエストから日付を取得（指定がない場合は今日）
        $dateParam = $request->input('date');
        $date = $dateParam ? Carbon::parse($dateParam) : Carbon::today();

        // 2. 前日・翌日の日付文字列を生成（URLのクエリパラメータ用）
        $previousDay = $date->copy()->subDay()->format('Y-m-d');
        $nextDay = $date->copy()->addDay()->format('Y-m-d');

        // 3. 全ユーザーを取得
        $users = User::all();

        // 4. 指定された日付の全ユーザーの勤怠レコードを休憩データ（breakTimes）と一緒に取得
        $attendanceRecords = AttendanceRecord::with('breakTimes')
            ->where('date', $date->toDateString())
            ->get();

        // 5. 各レコードの休憩時間と合計勤務時間を計算して動的に付与
        foreach ($attendanceRecords as $attendance) {
            $metrics = $this->calculateAttendanceMetrics($attendance);
            
            // ビューでプロパティとして扱えるようにセット
            $attendance->total_break_time = $metrics['total_break_time'];
            $attendance->total_time = $metrics['total_time'];
        }

        // 6. ビューに変数を渡して表示
        return view('admin.admin-attendance-list', compact(
            'date',
            'previousDay',
            'nextDay',
            'users',
            'attendanceRecords'
        ));
    }

    /**
     * 休憩時間の合計と総勤務時間を計算するプライベートメソッド
     */
    private function calculateAttendanceMetrics($attendance)
    {
        if (!$attendance || !$attendance->clock_in || !$attendance->clock_out) {
            return [
                'total_break_time' => null,
                'total_time' => null,
            ];
        }

        // 1. 休憩時間の合計（分）を計算
        $totalBreakMinutes = 0;
        foreach ($attendance->breakTimes as $break) {
            if ($break->break_in && $break->break_out) {
                $breakIn = Carbon::parse($break->break_in);
                $breakOut = Carbon::parse($break->break_out);
                $totalBreakMinutes += $breakIn->diffInMinutes($breakOut);
            }
        }

        // 2. 総勤務時間（出勤〜退勤の分 - 休憩時間の分）を計算
        $clockIn = Carbon::parse($attendance->clock_in);
        $clockOut = Carbon::parse($attendance->clock_out);
        $totalWorkMinutes = $clockIn->diffInMinutes($clockOut) - $totalBreakMinutes;

        if ($totalWorkMinutes < 0) {
            $totalWorkMinutes = 0;
        }

        // 3. ビュー側の Carbon::parse()->format('G:i') に対応する形式（H:i:s）に変換
        $breakHours = floor($totalBreakMinutes / 60);
        $breakMins = $totalBreakMinutes % 60;
        $totalBreakTimeStr = sprintf('%02d:%02d:00', $breakHours, $breakMins);

        $workHours = floor($totalWorkMinutes / 60);
        $workMins = $totalWorkMinutes % 60;
        $totalTimeStr = sprintf('%02d:%02d:00', $workHours, $workMins);

        return [
            'total_break_time' => $totalBreakTimeStr,
            'total_time' => $totalTimeStr,
        ];
    }

    /**
     * 1. 管理者用 勤怠詳細画面を表示 (GET: /attendance/{id})
     */
    public function show($id)
    {
        // 勤怠モデルの変数を $attendance に統一
        $attendance = AttendanceRecord::findOrFail($id);

        $user = $attendance->user;
        $breakTimes = $attendance->breakTimes;

        // ビュー側が $attendanceRecord という変数名で参照しているため整形
        $attendanceRecord = [
            'id' => $attendance->id,
            'year' => Carbon::parse($attendance->date)->format('Y年'),
            'date' => Carbon::parse($attendance->date)->format('m月d日'),
            'clock_in' => $attendance->clock_in ? Carbon::parse($attendance->clock_in)->format('H:i') : '',
            'clock_out' => $attendance->clock_out ? Carbon::parse($attendance->clock_out)->format('H:i') : '',
            'breaks' => $breakTimes->map(function ($breakTime) {
                return [
                    'break_in' => $breakTime->break_in ? Carbon::parse($breakTime->break_in)->format('H:i') : '',
                    'break_out' => $breakTime->break_out ? Carbon::parse($breakTime->break_out)->format('H:i') : '',
                ];
            })->toArray(),
            'comment' => $attendance->comment ?? '',
        ];

        // ビュー名に合わせて 'admin.admin-detail' を指定
        return view('admin.admin-detail', compact('attendanceRecord', 'user'));
    }

    /**
     * 2. 管理者用 修正処理 (POST: /attendance/{id})
     */
    public function update(AttendanceCorrectionRequest $request, $id)
    {
        // 修正対象の勤怠モデルを $attendance として取得
        $attendance = AttendanceRecord::findOrFail($id);

        // 出勤・退勤・備考の更新
        $attendance->update([
            'clock_in' => $request->input('new_clock_in'),
            'clock_out' => $request->input('new_clock_out'),
            'comment' => $request->input('comment'),
        ]);

        // 休憩時間の更新（既存をリセットして再登録）
        $newBreakIns = $request->input('new_break_in', []);
        $newBreakOuts = $request->input('new_break_out', []);

        $attendance->breakTimes()->delete();

        foreach ($newBreakIns as $index => $breakIn) {
            $breakOut = $newBreakOuts[$index] ?? null;
            
            // 開始と終了の両方が入力されている場合のみ保存
            if (!empty($breakIn) && !empty($breakOut)) {
                $attendance->breakTimes()->create([
                    'break_in' => $breakIn,
                    'break_out' => $breakOut,
                ]);
            }
        }

        // 更新後は詳細画面（または一覧など適切な画面）へリダイレクト
        // ※ url() や命名規則に合わせて調整してください
        return redirect('/attendance/' . $id)->with('success', '勤怠情報を修正しました。');
    }
}
