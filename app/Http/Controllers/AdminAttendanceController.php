<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AdminAttendanceController extends Controller
{
    /**
     * 管理者用 勤怠一覧画面（日ごと）
     */
    public function index(Request $request)
    {
        // ⭕ Gate::guard ではなく Gate::forUser に管理者ガードのユーザーを渡す
        if (! Gate::forUser(Auth::guard('admin')->user())->allows('viewAnyAdmin', AttendanceRecord::class)) {
            abort(403, '管理者権限がありません。');
        }

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
        if (! $attendance || ! $attendance->clock_in || ! $attendance->clock_out) {
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

        // ⭕ Gate::guard ではなく Gate::forUser に管理者ガードのユーザーを渡す
        if (! Gate::forUser(Auth::guard('admin')->user())->allows('view', $attendance)) {
            abort(403, '管理者権限がありません。');
        }

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
     * 管理者用 修正処理 (POST: admin/attendance/{id} など)
     */
    public function update(AttendanceCorrectionRequest $request, $id)
    {
        // 修正対象の勤怠モデルを取得
        $attendance = AttendanceRecord::findOrFail($id);

        // ⭕ Gate::guard ではなく Gate::forUser に管理者ガードのユーザーを渡す
        if (! Gate::forUser(Auth::guard('admin')->user())->allows('modify', $attendance)) {
            abort(403, '管理者権限がありません。');
        }

        // 基準となる日付文字列 (例: '2026-05-10')
        $dateStr = Carbon::parse($attendance->date)->format('Y-m-d');

        // ビューから送られてくる 'new_clock_in' / 'new_clock_out' を取得し、DB用の日時形式に変換
        $clockIn = $request->input('new_clock_in') ? "{$dateStr} {$request->input('new_clock_in')}:00" : null;
        $clockOut = $request->input('new_clock_out') ? "{$dateStr} {$request->input('new_clock_out')}:00" : null;

        // 1. 勤怠テーブル（attendance_records）の出勤・退勤・備考を直接更新
        $attendance->update([
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'comment' => $request->input('comment'),
        ]);

        // 2. 休憩時間の更新（既存をリセットして再登録）
        // ビューからは 'new_break_in' と 'new_break_out' という名前で配列で送られてくる
        $breakIns = $request->input('new_break_in', []);
        $breakOuts = $request->input('new_break_out', []);

        $attendance->breakTimes()->delete();

        foreach ($breakIns as $index => $breakIn) {
            $breakOut = $breakOuts[$index] ?? null;

            // 開始と終了の両方が入力されている場合のみ、日付と結合して保存
            if (! empty($breakIn) && ! empty($breakOut)) {
                $formattedBreakIn = "{$dateStr} {$breakIn}:00";
                $formattedBreakOut = "{$dateStr} {$breakOut}:00";

                $attendance->breakTimes()->create([
                    'break_in' => $formattedBreakIn,
                    'break_out' => $formattedBreakOut,
                ]);
            }
        }

        // 更新後は適切な詳細画面へリダイレクト
        return redirect()->to('admin/attendance/'.$id)->with('success', '勤怠情報を修正しました。');
    }
}
