<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Fluent; // ← これを追加
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;

class AdminStaffController extends Controller
{
/**
     * 管理者用 スタッフ一覧画面を表示
     */
    public function index()
    {
        // ⭕ Gate::guard ではなく Gate::forUser に管理者ガードのユーザーを渡す
        if (! Gate::forUser(Auth::guard('admin')->user())->allows('viewAny', User::class)) {
            abort(403, '管理者権限がありません。');
        }

        // 全スタッフを取得し、オブジェクトと配列の両方でアクセスできる Fluent に変換する
        $users = User::all()->map(function ($user) {
            return new Fluent($user->toArray());
        });

        // ビューにデータを渡して表示（ビュー側は一切変更不要）
        return view('admin.staff-list', compact('users'));
    }

/**
     * 指定スタッフの月次勤怠一覧画面を表示 (GET: /admin/attendance/staff/{id})
     */
    public function show(Request $request, $id)
    {
        // ⭕ Gate::guard ではなく Gate::forUser に管理者ガードのユーザーを渡す
        if (! Gate::forUser(Auth::guard('admin')->user())->allows('viewAnyAdmin', AttendanceRecord::class)) {
            abort(403, '管理者権限がありません。');
        }
        
        // 1. 対象のユーザーを取得
        $user = User::findOrFail($id);

        // 2. リクエストから年月を取得（指定がない場合は今月）
        $dateParam = $request->input('date');
        $date = $dateParam ? Carbon::parse($dateParam)->startOfMonth() : Carbon::today()->startOfMonth();

        // 3. 前月・翌月の年月文字列を生成
        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        // 4. 該当月の開始日と終了日を設定
        $startDate = $date->copy()->startOfMonth();
        $endDate = $date->copy()->endOfMonth();

        // 5. 該当月の勤怠レコードを休憩データ（breakTimes）と一緒に取得
        $attendanceRecords = AttendanceRecord::with('breakTimes')
            ->where('user_id', $user->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->keyBy('date');

        // 6. 1日〜月末までの全日分のデータを整形
        $formattedAttendanceRecords = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $dateString = $currentDate->toDateString();
            $record = $attendanceRecords->get($dateString);

            // 休憩時間と合計勤務時間を計算
            $metrics = $this->calculateAttendanceMetrics($record);

            $formattedAttendanceRecords[] = [
                'id' => $record ? $record->id : null,
                'date' => $currentDate->format('m月d日'),
                'clock_in' => $record && $record->clock_in ? Carbon::parse($record->clock_in)->format('H:i') : '',
                'clock_out' => $record && $record->clock_out ? Carbon::parse($record->clock_out)->format('H:i') : '',
                'total_break_time' => $metrics['total_break_time'],
                'total_time' => $metrics['total_time'],
            ];

            $currentDate->addDay();
        }

        return view('admin.staff-attendance-list', compact(
            'user',
            'date',
            'previousMonth',
            'nextMonth',
            'formattedAttendanceRecords'
        ));
    }

    /**
     * スタッフ別月次勤怠のCSV出力機能 (POST: /export)
     */
    public function export(Request $request)
    {
        $userId = $request->input('user_id');
        $yearMonth = $request->input('year_month'); // 例: '2026-10'

        $user = User::findOrFail($userId);
        $startDate = Carbon::parse($yearMonth)->startOfMonth();
        $endDate = Carbon::parse($yearMonth)->endOfMonth();

        // 対象月の勤怠データを取得
        $attendanceRecords = AttendanceRecord::with('breakTimes')
            ->where('user_id', $user->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->keyBy('date');

        $csvFileName = 'attendance_' . $user->id . '_' . $yearMonth . '.csv';

        $callback = function () use ($startDate, $endDate, $attendanceRecords, $user) {
            $file = fopen('php://output', 'w');

            // 1行目（ヘッダー）
            fputcsv($file, ['日付', '名前', '出勤', '退勤', '休憩', '合計']);

            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                $dateString = $currentDate->toDateString();
                $record = $attendanceRecords->get($dateString);

                // 計算処理を共通メソッドで実行
                $metrics = $this->calculateAttendanceMetrics($record);

                // CSV用に時間をフォーマット（G:i形式などに変換）
                $formattedBreakTime = $metrics['total_break_time'] ? Carbon::parse($metrics['total_break_time'])->format('G:i') : '';
                $formattedTotalTime = $metrics['total_time'] ? Carbon::parse($metrics['total_time'])->format('G:i') : '';

                fputcsv($file, [
                    $currentDate->format('Y/m/d'),
                    $user->name,
                    $record && $record->clock_in ? Carbon::parse($record->clock_in)->format('H:i') : '',
                    $record && $record->clock_out ? Carbon::parse($record->clock_out)->format('H:i') : '',
                    $formattedBreakTime,
                    $formattedTotalTime,
                ]);

                $currentDate->addDay();
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $csvFileName . '"',
        ]);
    }

    /**
     * 休憩時間の合計と総勤務時間を計算するプライベートメソッド
     */
    private function calculateAttendanceMetrics($record)
    {
        if (!$record || !$record->clock_in || !$record->clock_out) {
            return [
                'total_break_time' => null,
                'total_time' => null,
            ];
        }

        // 1. 休憩時間の合計（分）を計算
        $totalBreakMinutes = 0;
        foreach ($record->breakTimes as $break) {
            if ($break->break_in && $break->break_out) {
                $breakIn = Carbon::parse($break->break_in);
                $breakOut = Carbon::parse($break->break_out);
                $totalBreakMinutes += $breakIn->diffInMinutes($breakOut);
            }
        }

        // 2. 総勤務時間（出勤〜退勤の分 - 休憩時間の分）を計算
        $clockIn = Carbon::parse($record->clock_in);
        $clockOut = Carbon::parse($record->clock_out);
        $totalWorkMinutes = $clockIn->diffInMinutes($clockOut) - $totalBreakMinutes;

        if ($totalWorkMinutes < 0) {
            $totalWorkMinutes = 0;
        }

        // 3. 分を 'H:i:s' 形式の文字列に変換
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
}
