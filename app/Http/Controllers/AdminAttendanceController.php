<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminAttendanceController extends Controller
{
    /**
     * 管理者用 勤怠一覧画面（日ごと）を表示する
     *
     * @param  Request  $request  HTTPリクエストインスタンス
     * @return View 勤怠一覧画面のビューインスタンス
     */
    public function index(Request $request): View
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
     *
     * @param  AttendanceRecord|null  $attendance  判定対象の勤怠レコード
     * @return array{total_break_time: ?string, total_time: ?string} 休憩時間と総勤務時間の文字列表現配列
     */
    private function calculateAttendanceMetrics(?AttendanceRecord $attendance): array
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
     * 管理者用 勤怠詳細画面を表示する (GET: /attendance/{id})
     *
     * @param  int|string  $id  勤怠レコードID
     * @return View 勤怠詳細画面のビューインスタンス
     */
    public function show(int|string $id): View
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
     * 管理者による勤怠情報の修正・更新処理を実行する
     *
     * @param  AttendanceCorrectionRequest  $request  バリデーション済みリクエスト
     * @param  int|string  $id  勤怠レコードID
     * @return RedirectResponse 完了後のリダイレクトレスポンス
     */
    public function update(AttendanceCorrectionRequest $request, int|string $id): RedirectResponse
    {
        $attendance = AttendanceRecord::with('breakTimes')->findOrFail($id);

        // 管理者権限の認可チェック
        if (! Gate::forUser(Auth::guard('admin')->user())->allows('modify', $attendance)) {
            abort(403, '管理者権限がありません。');
        }

        $dateStr = Carbon::parse($attendance->date)->format('Y-m-d');

        // 管理者が入力した新しい値
        $clockIn = $request->input('new_clock_in') ? "{$dateStr} {$request->input('new_clock_in')}:00" : null;
        $clockOut = $request->input('new_clock_out') ? "{$dateStr} {$request->input('new_clock_out')}:00" : null;
        $comment = $request->input('comment');

        $breakIns = $request->input('new_break_in', []);
        $breakOuts = $request->input('new_break_out', []);

        DB::transaction(function () use ($attendance, $dateStr, $clockIn, $clockOut, $comment, $breakIns, $breakOuts) {

            // 1. 修正申請（AttendanceCorrection）の作成または更新
            $existingCorrection = AttendanceCorrection::where('attendance_record_id', $attendance->id)
                ->where('approval_status', '承認待ち')
                ->first();

            if ($existingCorrection) {
                $existingCorrection->update([
                    'new_clock_in' => $clockIn,
                    'new_clock_out' => $clockOut,
                    'comment' => $comment,
                    'approval_status' => '承認済み',
                ]);
                $correction = $existingCorrection;
            } else {
                $correction = AttendanceCorrection::create([
                    'attendance_record_id' => $attendance->id,
                    'new_clock_in' => $clockIn,
                    'new_clock_out' => $clockOut,
                    'comment' => $comment,
                    'approval_status' => '承認済み',
                ]);
            }

            // 2. 【修正休憩テーブル（proposalBreaks）】の差分同期処理
            // 既存の修正休憩を取得しておく
            $existingProposalBreaks = $correction->proposalBreaks()->get();
            foreach ($breakIns as $index => $breakIn) {
                $breakOut = $breakOuts[$index] ?? null;

                if (! empty($breakIn) || ! empty($breakOut)) {
                    $formattedIn = ! empty($breakIn) ? "{$dateStr} {$breakIn}:00" : null;
                    $formattedOut = ! empty($breakOut) ? "{$dateStr} {$breakOut}:00" : null;

                    // すでに該当インデックス（順番）にレコードが存在すれば上書き（修正）、なければ新規作成（追加）
                    if (isset($existingProposalBreaks[$index])) {
                        $existingProposalBreaks[$index]->update([
                            'new_break_in' => $formattedIn,
                            'new_break_out' => $formattedOut,
                        ]);
                    } else {
                        $correction->proposalBreaks()->create([
                            'new_break_in' => $formattedIn,
                            'new_break_out' => $formattedOut,
                        ]);
                    }
                }
            }
            // フォームから送られてこなくなった（削除された）余分な既存レコードがあれば削除する
            if ($existingProposalBreaks->count() > count($breakIns)) {
                $correction->proposalBreaks()->skip(count($breakIns))->take($existingProposalBreaks->count() - count($breakIns))->delete();
            }

            // 3. 【大元の勤怠テーブル（attendance_records）】を更新
            $attendance->update([
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
                'comment' => $comment,
            ]);

            // 4. 【大元の休憩時間テーブル（break_times）】の差分同期処理
            $existingBreakTimes = $attendance->breakTimes()->get();
            foreach ($breakIns as $index => $breakIn) {
                $breakOut = $breakOuts[$index] ?? null;

                if (! empty($breakIn) || ! empty($breakOut)) {
                    $formattedIn = ! empty($breakIn) ? "{$dateStr} {$breakIn}:00" : null;
                    $formattedOut = ! empty($breakOut) ? "{$dateStr} {$breakOut}:00" : null;

                    // すでに該当インデックスにレコードが存在すれば上書き、なければ新規作成
                    if (isset($existingBreakTimes[$index])) {
                        $existingBreakTimes[$index]->update([
                            'break_in' => $formattedIn,
                            'break_out' => $formattedOut,
                        ]);
                    } else {
                        $attendance->breakTimes()->create([
                            'break_in' => $formattedIn,
                            'break_out' => $formattedOut,
                        ]);
                    }
                }
            }
            // 削除された行があれば削除
            if ($existingBreakTimes->count() > count($breakIns)) {
                $attendance->breakTimes()->skip(count($breakIns))->take($existingBreakTimes->count() - count($breakIns))->delete();
            }
        });

        $userId = $attendance->user_id;

        return redirect()->to('admin/attendance/staff/'.$userId)->with('success', '勤怠情報を修正しました。');
    }
}
