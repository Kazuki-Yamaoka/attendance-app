<?php

namespace App\Policies;

use App\Models\AttendanceCorrection;
use App\Models\User;

class AttendanceCorrectionPolicy
{
    /**
     * 一般ユーザー用: 自分の修正申請一覧の閲覧権限
     */
    public function viewAny(User $user)
    {
        // ログインしていればOK（コントローラー側で自分のデータに絞り込む）
        return true;
    }

    /**
     * 管理者用: 全ユーザーの修正申請一覧の閲覧権限
     */
    public function viewAnyAdmin(User $user)
    {
        // 管理者のみ許可
        return $user->is_admin;
    }

    /**
     * 詳細・承認画面（show / approve）の権限
     */
    public function view(User $user, AttendanceCorrection $correction)
    {
        // 管理者であれば無条件でOK（一般ユーザーはそもそもこの画面に来ない想定）
        return $user->is_admin;
    }

    /**
     * 承認処理（approve）の権限
     */
    public function approve(User $user, AttendanceCorrection $correction)
    {
        // 承認も管理者のみ
        return $user->is_admin;
    }
}
