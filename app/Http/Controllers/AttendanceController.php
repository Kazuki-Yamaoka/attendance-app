<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceCorrectionBreaktime;
use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    /**
     * 勤怠打刻・登録画面を表示する
     *
     * @return View 打刻画面のビューインスタンス
     */
    public function index(): View
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

    /**
     * 打刻アクションの総合窓口（振り付け役）：ボタンの合言葉を見て、対応するメソッドを呼び出す
     *
     * @param  Request  $request  HTTPリクエストインスタンス
     * @return RedirectResponse リダイレクトレスポンス
     */
    public function store(Request $request): RedirectResponse
    {
        return match ($request->input('action')) {
            'clock_in' => $this->storeClockIn($request),
            'clock_out' => $this->storeClockOut($request),
            'break_in' => $this->storeBreakIn($request),
            'break_out' => $this->storeBreakOut($request),
            default => abort(400),
        };
    }

    // --- 各処理専用のプライベートメソッド ---

    /**
     * 出勤打刻を保存する
     *
     * @param  Request  $request  HTTPリクエストインスタンス
     * @return RedirectResponse リダイレクトレスポンス
     */
    private function storeClockIn(Request $request): RedirectResponse
    {
        AttendanceRecord::create([
            'user_id' => Auth::id(),
            'date' => Carbon::today()->toDateString(),
            'clock_in' => Carbon::now(),
        ]);

        return redirect()->back();
    }

    /**
     * 退勤打刻を保存する
     *
     * @param  Request  $request  HTTPリクエストインスタンス
     * @return RedirectResponse リダイレクトレスポンス
     */
    private function storeClockOut(Request $request): RedirectResponse
    {
        // ① まだ退勤していない未完了のレコードを探す
        $record = AttendanceRecord::where('user_id', Auth::id())
            ->whereNull('clock_out')
            ->latest()
            ->first();

        // ② もし「未退勤のレコードがそもそも無いのに退勤ボタンが押された」場合（不正や2回押し）
        if (! $record) {
            return redirect()->back()->with('error', 'すでに退勤済みか、出勤していません。');
        }

        // ③ 問題なければ退勤時間を更新
        $record->update([
            'clock_out' => Carbon::now(),
        ]);

        return redirect()->back();
    }

    /**
     * 休憩開始打刻を保存する
     *
     * @param  Request  $request  HTTPリクエストインスタンス
     * @return RedirectResponse リダイレクトレスポンス
     */
    private function storeBreakIn(Request $request): RedirectResponse
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

    /**
     * 休憩終了打刻を保存する
     *
     * @param  Request  $request  HTTPリクエストインスタンス
     * @return RedirectResponse リダイレクトレスポンス
     */
    private function storeBreakOut(Request $request): RedirectResponse
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
        if (! $breakTime) {
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

    /**
     * 一般ユーザー向け勤怠一覧画面を表示する
     *
     * @param  Request  $request  HTTPリクエストインスタンス
     * @return View 勤怠一覧画面のビューインスタンス
     */
    public function list(Request $request): View
    {
        $this->authorize('viewAny', AttendanceRecord::class);

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
     *
     * @param  \DateTimeInterface  $day  判定対象の日付インスタンス
     * @param  AttendanceRecord|null  $record  勤怠レコード、存在しない場合はnull
     * @return array<string, mixed> フォーマット済み勤怠データの配列
     */
    private function formatDailyRecord(\DateTimeInterface $day, ?AttendanceRecord $record): array
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

        // $day が DateTimeInterface になっているので、Carbon::instance() でラップすると安全に format が使えます
        $carbonDay = Carbon::instance($day);

        return [
            'id' => $recordId,
            'date' => $carbonDay->format('Y/m/d'),
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'total_break_time' => $breakTimeFormatted,
            'total_time' => $totalTimeFormatted,
        ];
    }

    /**
     * 休憩時間の合計（分）を計算する
     *
     * @param  iterable<BreakTime>  $breakTimes  休憩時間モデルのコレクションまたは配列
     * @return int 休憩時間の合計（分）
     */
    private function calculateTotalBreakMinutes(iterable $breakTimes): int
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
     *
     * @param  string  $clockIn  出勤時刻
     * @param  string  $clockOut  退勤時刻
     * @param  int  $breakMinutes  休憩時間の合計（分）
     * @return int 実労働時間（分）
     */
    private function calculateTotalWorkMinutes(string $clockIn, string $clockOut, int $breakMinutes): int
    {
        $workMinutes = Carbon::parse($clockIn)->diffInMinutes(Carbon::parse($clockOut));

        // 全体時間から休憩時間を引く（マイナスにならないよう max(0, ...)）
        return max(0, $workMinutes - $breakMinutes);
    }

    /**
     * 分単位の数値を「H:i」形式（例: "08:30"）の文字列に変換する
     *
     * @param  int  $minutes  分単位の数値
     * @return string|null 「H:i」形式の時刻文字列
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
     *
     * @param  int|string  $id  勤怠レコードID
     * @return View 勤怠詳細画面のビューインスタンス
     */
    public function show(int|string $id): View
    {
        $user = Auth::user();

        // 1. 大元の「勤怠レコード」を基準に取得する（不正アクセス防止のため user_id もチェック）
        // 合わせて、最新の「承認待ち」の修正申請があれば一緒にロードする
        $attendanceRecord = AttendanceRecord::where('user_id', $user->id)
            ->with([
                'breakTimes',
                'attendanceCorrections' => function ($query) {
                    $query->where('approval_status', '承認待ち')
                        ->with('attendanceCorrectionBreaktimes');
                },
            ])
            ->findOrFail($id);

        $this->authorize('view', $attendanceRecord);

        // 2. 紐づく「承認待ち」の申請データを取得（なければ null）
        $pendingApplication = $attendanceRecord->attendanceCorrections->first();

        // 3. 承認待ちの申請が存在するかどうかで表示データを切り替える
        $isPending = ! is_null($pendingApplication);

        $targetClockIn = $isPending ? $pendingApplication->new_clock_in : $attendanceRecord->clock_in;
        $targetClockOut = $isPending ? $pendingApplication->new_clock_out : $attendanceRecord->clock_out;
        $targetBreaks = $isPending ? $pendingApplication->attendanceCorrectionBreaktimes : $attendanceRecord->breakTimes;
        $targetComment = $isPending ? $pendingApplication->comment : $attendanceRecord->comment;

        // 4. 休憩データの整形
        $breaks = [];
        foreach ($targetBreaks as $break) {
            $in = $break->new_break_in ?? $break->break_in ?? null;
            $out = $break->new_break_out ?? $break->break_out ?? null;

            $breaks[] = [
                'break_in' => $in ? Carbon::parse($in)->format('H:i') : '',
                'break_out' => $out ? Carbon::parse($out)->format('H:i') : '',
            ];
        }

        $recordDate = Carbon::parse($attendanceRecord->date);

        // 5. ビューへ渡す `$data` 配列
        $data = [
            'id' => $attendanceRecord->id, // 勤怠レコードのID
            'application' => $pendingApplication, // 承認待ちならオブジェクト、なければ null
            'year' => $recordDate->format('Y年'),
            'date' => $recordDate->format('n月j日'),
            'clock_in' => $targetClockIn ? Carbon::parse($targetClockIn)->format('H:i') : '',
            'clock_out' => $targetClockOut ? Carbon::parse($targetClockOut)->format('H:i') : '',
            'breaks' => $breaks,
            'comment' => $targetComment ?? '',
        ];

        return view('user.user-detail', compact('user', 'data', 'attendanceRecord'));
    }

    /**
     * 一般ユーザーによる勤怠修正申請を登録・更新する
     *
     * @param  AttendanceCorrectionRequest  $request  バリデーション済みリクエスト
     * @param  int|string  $id  勤怠レコードID
     * @return RedirectResponse 完了後のリダイレクトレスポンス
     */
    public function update(AttendanceCorrectionRequest $request, int|string $id): RedirectResponse
    {
        $user = Auth::user();

        // 対象の勤怠レコードを取得
        $attendanceRecord = AttendanceRecord::where('user_id', $user->id)->findOrFail($id);
        $recordDate = $attendanceRecord->date;

        // 出退勤日時の整形
        $newClockIn = $request->input('new_clock_in');
        $newClockInDateTime = $newClockIn ? "{$recordDate} {$newClockIn}:00" : null;

        $newClockOut = $request->input('new_clock_out');
        $newClockOutDateTime = $newClockOut ? "{$recordDate} {$newClockOut}:00" : null;

        // トランザクション内で安全に一括保存
        DB::transaction(function () use ($request, $attendanceRecord, $recordDate, $newClockInDateTime, $newClockOutDateTime) {

            // 1. 新しい修正申請（親）のレコードを作成
            $correction = AttendanceCorrection::create([
                'attendance_record_id' => $attendanceRecord->id,
                'new_clock_in' => $newClockInDateTime,
                'new_clock_out' => $newClockOutDateTime,
                'comment' => $request->input('comment'),
                'approval_status' => '承認待ち',
            ]);

            // 2. 複数の休憩データを取得してループで保存
            $breakIns = $request->input('new_break_in', []);
            $breakOuts = $request->input('new_break_out', []);

            foreach ($breakIns as $index => $breakIn) {
                $breakOut = $breakOuts[$index] ?? null;

                if (! empty($breakIn) && ! empty($breakOut)) {
                    AttendanceCorrectionBreaktime::create([
                        'attendance_correction_id' => $correction->id,
                        'new_break_in' => "{$recordDate} {$breakIn}:00",
                        'new_break_out' => "{$recordDate} {$breakOut}:00",
                    ]);
                }
            }
        });

        // 完了後に詳細画面へリダイレクト
        return redirect()->route('attendance.show', ['id' => $id])->with('success', '勤怠の修正申請を送信しました。');
    }
}
