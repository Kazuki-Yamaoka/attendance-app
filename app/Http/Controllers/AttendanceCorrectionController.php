<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AttendanceCorrectionController extends Controller
{
    public function index()
    {
        // 1. 管理者ガード（'admin'）でログインしているかチェック
        if (Auth::guard('admin')->check()) {

            // ⭕ Gate::guard ではなく Gate::forUser に管理者ガードのユーザーを渡す
            if (! Gate::forUser(Auth::guard('admin')->user())->allows('viewAnyAdmin', AttendanceCorrection::class)) {
                abort(403, '管理者権限がありません。');
            }

            $applications = AttendanceCorrection::with(['attendanceRecord.user'])
                ->orderBy('created_at', 'desc')
                ->get();

            return view('admin.admin-application-list', compact('applications'));
        }

        // 2. 一般ユーザーガード（デフォルト 'web'）でログインしているかチェック
        if (Auth::check()) {
            $this->authorize('viewAny', AttendanceCorrection::class);

            $user = Auth::user();

            // 修正申請と大元の勤怠レコードをセットで取得
            $corrections = AttendanceCorrection::with('attendanceRecord')
                ->whereHas('attendanceRecord', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->orderBy('created_at', 'desc')
                ->get();

            $formattedApplications = $corrections->map(function ($correction) {
                // 勤怠レコードが存在するかチェック
                $attendance = $correction->attendanceRecord;

                $targetDate = '';
                if ($attendance && $attendance->date) {
                    $targetDate = Carbon::parse($attendance->date)->format('Y/m/d');
                }

                return [
                    // ★【超重要】詳細画面が「勤怠レコードのID」を求めているため、ここを勤怠IDにする！
                    'id' => $attendance ? $attendance->id : $correction->id,

                    'approval_status' => $correction->approval_status,
                    'date' => $targetDate, // 勤怠テーブルの正確な日付
                    'comment' => $correction->comment,
                    'application_date' => $correction->created_at->format('Y/m/d'),
                ];
            });

            return view('user.user-application-list', compact('user', 'formattedApplications'));
        }

        // 3. どちらもログインしていなければログイン画面へ
        return redirect()->route('login');
    }

    /**
     * 修正申請詳細（承認画面）を表示する
     */
    public function show($id)
    {
        // 修正申請データに紐づく勤怠レコード、ユーザー、休憩修正データを一緒に取得
        $application = AttendanceCorrection::with([
            'attendanceRecord.user',
            'proposalBreaks', // モデル側で定義したリレーション名
        ])->findOrFail($id);

        // 1. 日付（new_date）を Carbon インスタンスとして動的にセット
        if ($application->attendanceRecord && $application->attendanceRecord->date) {
            $application->new_date = Carbon::parse($application->attendanceRecord->date);
        } else {
            $application->new_date = Carbon::now();
        }

        // ⭕ Gate::guard ではなく Gate::forUser に管理者ガードのユーザーを渡す
        if (! Gate::forUser(Auth::guard('admin')->user())->allows('view', $application)) {
            abort(403, '管理者権限がありません。');
        }

        // 2. 出勤時間（new_clock_in）を "H:i" 形式に変換
        if ($application->new_clock_in) {
            $application->new_clock_in = Carbon::parse($application->new_clock_in)->format('H:i');
        }

        // 3. 退勤時間（new_clock_out）を "H:i" 形式に変換
        if ($application->new_clock_out) {
            $application->new_clock_out = Carbon::parse($application->new_clock_out)->format('H:i');
        }

        // 4. 休憩修正データのプロパティ名をビューに合わせてマッピング
        // （データベースの new_break_in / new_break_out を、ビューが求めている break_in / break_out に割り当てる）
        foreach ($application->proposalBreaks as $break) {
            $break->break_in = $break->new_break_in;
            $break->break_out = $break->new_break_out;
        }

        // ビュー側で $user->name として参照するため、紐づくユーザー情報を取得して渡す
        $user = $application->attendanceRecord->user ?? null;

        return view('admin.admin-application-detail', compact('application', 'user'));
    }

    public function approve($attendance_correct_request_id)
    {
        \DB::transaction(function () use ($attendance_correct_request_id) {
            $application = AttendanceCorrection::with('proposalBreaks')->findOrFail($attendance_correct_request_id);
            $attendanceRecord = AttendanceRecord::findOrFail($application->attendance_record_id);

            // ⭕ Gate::guard ではなく Gate::forUser に管理者ガードのユーザーを渡す
            if (! Gate::forUser(Auth::guard('admin')->user())->allows('approve', $application)) {
                abort(403, '管理者権限がありません。');
            }

            // 1. 元の勤怠レコードを上書き更新
            $attendanceRecord->update([
                'clock_in' => $application->new_clock_in,
                'clock_out' => $application->new_clock_out,
            ]);

            // 2. 元の休憩レコードを一度すべて削除し、修正申請側の休憩で置き換える
            $attendanceRecord->breakTimes()->delete();

            foreach ($application->proposalBreaks as $proposalBreak) {
                BreakTime::create([
                    'attendance_record_id' => $attendanceRecord->id,
                    'break_in' => $proposalBreak->new_break_in,
                    'break_out' => $proposalBreak->new_break_out,
                ]);
            }

            // 3. 修正申請のステータスを「承認済み」に変更して保存
            $application->update([
                'approval_status' => '承認済み',
            ]);
        });

        return redirect()->route('application.show')->with('success', '修正申請を承認しました。');
    }
}
