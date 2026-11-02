<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceCorrectionBreaktime;
use App\Models\BreakTime;
use App\Http\Requests\AttendanceRequest;
use App\Http\Requests\AttendanceCorrectionRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
     public function index()
    {
        $user = Auth::user(); // ログイン中のユーザー情報を取得

        // ① 今日の日付と現在時刻をLaravel標準の Carbon で取得する
        $now = Carbon::now();

        // ② 画面表示用にフォーマット（文字の形）を整えて変数に入れる
        $formattedDate = $now->translatedFormat('Y年m月d日（D）'); // 例: 2026年10月29日（木）など
        $formattedTime = $now->format('H:i');             // 例: 21:45 など

        // ③ compact関数やwithを使って、ビューにまとめて変数を送る
        return view('user.attendance-register', compact('user', 'formattedDate', 'formattedTime'));
    }
    
    // 総合窓口（振り付け役）：ボタンの合言葉を見て、対応するメソッドを呼び出す
    public function store(Request $request)
    {
        return match ($request->input('action')) {
            'clock_in'  => $this->storeClockIn($request),
            'clock_out' => $this->storeClockOut($request),
            'break_in'  => $this->storeBreakIn($request),
            'break_out' => $this->storeBreakOut($request),
            default     => abort(400),
        };
    }

    // --- 各処理専用のプライベートメソッド ---

    private function storeClockIn(Request $request)
    {
        AttendanceRecord::create([
            'user_id' => Auth::id(),
            'date' => Carbon::today()->toDateString(),
            'clock_in' => Carbon::now(),
        ]);

        return redirect()->back();
    }

    private function storeClockOut(Request $request)
    {
        // ① まだ退勤していない未完了のレコードを探す
        $record = AttendanceRecord::where('user_id', Auth::id())
            ->whereNull('clock_out')
            ->latest()
            ->first();

        // ② もし「未退勤のレコードがそもそも無いのに退勤ボタンが押された」場合（不正や2回押し）
        if (!$record) {
            return redirect()->back()->with('error', 'すでに退勤済みか、出勤していません。');
        }

        // ③ 問題なければ退勤時間を更新
        $record->update([
            'clock_out' => Carbon::now(),
        ]);

        return redirect()->back();
    }

    private function storeBreakIn(Request $request)
    {
        $record = AttendanceRecord::where('user_id', Auth::id())
            ->whereDate('date', Carbon::today())
            ->first();

        if ($record) {
            BreakTime::create([
                'attendance_record_id' => $record->id,
                'break_in' => Carbon::now(),
            ]);
        }

        return redirect()->back();
    }

    private function storeBreakOut(Request $request)
    {
        $record = AttendanceRecord::where('user_id', Auth::id())
            ->whereNull('clock_out')
            ->latest('id')
            ->first();

        
        $breakTime = BreakTime::where('attendance_record_id', $record->id)
            ->whereNull('break_out')
            ->latest()
            ->first();

        // ② もし「未退勤のレコードがそもそも無いのに退勤ボタンが押された」場合（不正や2回押し）
        if (!$breakTime) {
            return redirect()->back()->with('error', 'すでに休憩終了済みか、休憩開始していません。');
        }
            
            $breakTime->update([
                'break_out' => Carbon::now(),
            ]);

        return redirect()->back();
    }

    // ==========================================
    // 1. メインの司令塔（一覧画面表示）
    // ==========================================
    public function list(Request $request)
    {
        $user = Auth::user();

        // 基準日と月の前後の取得
        $date = $request->input('date') 
            ? Carbon::parse($request->input('date')) 
            : Carbon::now();

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        $startDate = $date->copy()->startOfMonth();
        $endDate = $date->copy()->endOfMonth();

        // 該当月の勤怠データを取得して日付キーの連想配列に変換
        $records = AttendanceRecord::where('user_id', $user->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->with('breakTimes')
            ->get()
            ->keyBy(fn ($item) => Carbon::parse($item->date)->format('Y-m-d'));

        // 1日〜月末までのカレンダー配列を組み立て
        $formattedAttendanceRecords = [];
        $period = new \DatePeriod($startDate, new \DateInterval('P1D'), $endDate->copy()->addDay());

        foreach ($period as $day) {
            $dateString = $day->format('Y-m-d');
            $record = $records->get($dateString);

            // 1日分のデータを組み立てる処理をメソッドに切り出し
            $formattedAttendanceRecords[] = $this->formatDailyRecord($day, $record);
        }

        return view('user.user-attendance-list', compact(
            'date',
            'previousMonth',
            'nextMonth',
            'formattedAttendanceRecords'
        ));
    }


    // ==========================================
    // 2. プライベートメソッド（部品としての切り出し）
    // ==========================================

    /**
     * 1日分の勤怠データをビュー用にフォーマットする
     */
    private function formatDailyRecord($day, ?AttendanceRecord $record): array
    {
        $clockIn = null;
        $clockOut = null;
        $breakTimeFormatted = null;
        $totalTimeFormatted = null;
        $recordId = null;

        if ($record) {
            $recordId = $record->id;
            $clockIn = $record->clock_in ? Carbon::parse($record->clock_in)->format('H:i') : null;
            $clockOut = $record->clock_out ? Carbon::parse($record->clock_out)->format('H:i') : null;

            // 休憩時間の合計（分）を計算
            $totalBreakMinutes = $this->calculateTotalBreakMinutes($record->breakTimes);
            $breakTimeFormatted = $this->formatMinutesToHours($totalBreakMinutes);

            // 勤務時間の合計（分）を計算
            if ($record->clock_in && $record->clock_out) {
                $totalWorkMinutes = $this->calculateTotalWorkMinutes(
                    $record->clock_in, 
                    $record->clock_out, 
                    $totalBreakMinutes
                );
                $totalTimeFormatted = $this->formatMinutesToHours($totalWorkMinutes);
            }
        }

        return [
            'id' => $recordId,
            'date' => $day->format('Y/m/d'),
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'total_break_time' => $breakTimeFormatted,
            'total_time' => $totalTimeFormatted,
        ];
    }

    /**
     * 休憩時間の合計（分）を計算する
     */
    private function calculateTotalBreakMinutes($breakTimes): int
    {
        $totalMinutes = 0;

        foreach ($breakTimes as $break) {
            if ($break->break_in && $break->break_out) {
                $totalMinutes += Carbon::parse($break->break_in)->diffInMinutes(Carbon::parse($break->break_out));
            }
        }

        return $totalMinutes;
    }

    /**
     * 実働時間の合計（分）を計算する
     */
    private function calculateTotalWorkMinutes(string $clockIn, string $clockOut, int $breakMinutes): int
    {
        $workMinutes = Carbon::parse($clockIn)->diffInMinutes(Carbon::parse($clockOut));
        
        // 全体時間から休憩時間を引く（マイナスにならないよう max(0, ...)）
        return max(0, $workMinutes - $breakMinutes);
    }

    /**
     * 分単位の数値を「H:i」形式（例: "08:30"）の文字列に変換する
     */
    private function formatMinutesToHours(int $minutes): ?string
    {
        if ($minutes < 0) {
            $minutes = 0;
        }

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        return sprintf('%d:%02d', $hours, $mins);
    }

    /**
     * 勤怠詳細・修正画面を表示する
     */
    public function show($id)
    {
        $user = Auth::user();

        // 1. 元の勤怠レコードを取得
        $attendanceRecord = AttendanceRecord::where('user_id', $user->id)
            ->with('breakTimes')
            ->findOrFail($id);

        // 2. 「修正申請テーブル」にこの勤怠のレコードが存在するか確認
        // レコードがあれば＝「申請中（承認待ち）」とみなす
        $pendingApplication = AttendanceCorrection::where('attendance_record_id', $attendanceRecord->id)
            ->with('attendanceCorrectionBreaktimes')
            ->first();

        // 3. 申請データが存在すれば「申請中の値」、なければ「元の勤怠データ」を表示のベースにする
        $targetClockIn = $pendingApplication ? $pendingApplication->clock_in : $attendanceRecord->clock_in;
        $targetClockOut = $pendingApplication ? $pendingApplication->clock_out : $attendanceRecord->clock_out;
        $targetBreaks = $pendingApplication ? $pendingApplication->attendanceCorrectionBreaktimes : $attendanceRecord->breakTimes;
        $targetComment = $pendingApplication ? $pendingApplication->comment : $attendanceRecord->comment;

        // 4. 休憩データの整形
        $breaks = [];
        foreach ($targetBreaks as $break) {
            $breaks[] = [
                'break_in' => $break->break_in ? Carbon::parse($break->break_in)->format('H:i') : '',
                'break_out' => $break->break_out ? Carbon::parse($break->break_out)->format('H:i') : '',
            ];
        }

        $recordDate = Carbon::parse($attendanceRecord->date);

        // 5. ビューへ渡す `$data` 配列
        $data = [
            'id' => $attendanceRecord->id,
            'application' => $pendingApplication, // レコードがあればオブジェクト、なければ null
            'year' => $recordDate->format('Y年'),
            'date' => $recordDate->format('n月j日'),
            'clock_in' => $targetClockIn ? Carbon::parse($targetClockIn)->format('H:i') : '',
            'clock_out' => $targetClockOut ? Carbon::parse($targetClockOut)->format('H:i') : '',
            'breaks' => $breaks,
            'comment' => $targetComment ?? '',
        ];

        return view('user.user-detail', compact('user', 'data'));
    }

    /**
     * 修正申請を保存する（存在すれば更新、なければ新規作成）
     */
    public function update(AttendanceCorrectionRequest $request, $id)
    {
        $user = Auth::user();

        // 対象の元レコードが存在するか確認
        $attendanceRecord = AttendanceRecord::where('user_id', $user->id)->findOrFail($id);

        // データの整合性を保つためDBトランザクションを使用
        DB::transaction(function () use ($request, $attendanceRecord) {
            
            // 1. 修正申請テーブルを更新または作成（丸ごと置き換え）
            $correction = AttendanceCorrection::updateOrCreate(
                [
                    'attendance_record_id' => $attendanceRecord->id,
                ],
                [
                    'user_id' => $attendanceRecord->user_id,
                    'date' => $attendanceRecord->date,
                    'clock_in' => $request->input('new_clock_in'),
                    'clock_out' => $request->input('new_clock_out'),
                    'comment' => $request->input('comment'),
                ]
            );

            // 2. 既存の紐づく修正用休憩データを一旦全削除して、新しく登録し直す
            $correction->attendanceCorrectionBreaktimes()->delete();

            $breakIns = $request->input('new_break_in', []);
            $breakOuts = $request->input('new_break_out', []);

            foreach ($breakIns as $index => $breakIn) {
                $breakOut = $breakOuts[$index] ?? null;
                // すべて NOT NULL で入力される前提：両方揃っている場合のみ作成
                if (!empty($breakIn) && !empty($breakOut)) {
                    AttendanceCorrectionBreaktime::create([
                        'attendance_correction_id' => $correction->id,
                        'break_in' => $breakIn,
                        'break_out' => $breakOut,
                    ]);
                }
            }
        });

        return redirect()->route('user.user-attendance.detail')->with('success', '勤怠の修正申請を送信しました。');
    }
}
