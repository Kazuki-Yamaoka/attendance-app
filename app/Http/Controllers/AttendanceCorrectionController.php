<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class AttendanceCorrectionController extends Controller
{
/**
     * 申請一覧画面（一般ユーザー・管理者 共通ルート）
     */
    public function index()
    {
        // 1. ログイン中のユーザーを取得
        $user = Auth::user();

        // 2. 管理者の場合の処理
        // ※プロジェクトの管理者判定カラムに合わせて条件を変更してください（例: $user->is_admin や $user->role === 'admin' など）
        if ($user->is_admin === true) {
            // 管理者の場合は全件取得（必要に応じてユーザー情報もロード）
            $corrections = AttendanceCorrection::with(['attendanceRecord.user'])
                ->orderBy('created_at', 'desc')
                ->get();

            $formattedApplications = $corrections->map(function ($correction) {
                return [
                    'id' => $correction->id,
                    'user_name' => $correction->attendanceRecord && $correction->attendanceRecord->user 
                        ? $correction->attendanceRecord->user->name 
                        : '', // 管理者画面で申請者名を表示する場合などに活用
                    'approval_status' => $correction->approval_status,
                    'date' => $correction->attendanceRecord 
                        ? Carbon::parse($correction->attendanceRecord->date)->format('Y/m/d') 
                        : '',
                    'comment' => $correction->comment,
                    'application_date' => $correction->created_at->format('Y/m/d'),
                ];
            });

            // 管理者用のビューを返す
            return view('admin.request.index', compact('user', 'formattedApplications'));
        }

        // 3. 一般ユーザーの場合の処理
        $corrections = AttendanceCorrection::with('attendanceRecord')
            ->whereHas('attendanceRecord', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $formattedApplications = $corrections->map(function ($correction) {
            return [
                'id' => $correction->id,
                'approval_status' => $correction->approval_status,
                'date' => $correction->attendanceRecord 
                    ? Carbon::parse($correction->attendanceRecord->date)->format('Y/m/d') 
                    : '',
                'comment' => $correction->comment,
                'application_date' => $correction->created_at->format('Y/m/d'),
            ];
        });

        // 一般ユーザー用のビューを返す
        return view('user.user-application-list', compact('user', 'formattedApplications'));
    }
}
