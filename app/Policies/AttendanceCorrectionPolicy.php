<?php

namespace App\Policies;

use App\Models\AttendanceCorrection;
use App\Models\User;

class AttendanceCorrectionPolicy
{
    /**
     * 一般ユーザー用: 自分の修正申請一覧の閲覧権限があるかどうかを判定する
     *
     * @param  User  $user  ログイン中のユーザー
     * @return bool 閲覧を許可する場合はtrue、それ以外はfalse
     */
    public function viewAny(User $user): bool
    {
        // ログインしていればOK（コントローラー側で自分のデータに絞り込む）
        return true;
    }

    /**
     * 管理者用: 全ユーザーの修正申請一覧の閲覧権限があるかどうかを判定する
     *
     * @param  User  $user  ログイン中のユーザー
     * @return bool 管理者の場合はtrue、それ以外はfalse
     */
    public function viewAnyAdmin(User $user): bool
    {
        // 管理者のみ許可
        return $user->is_admin;
    }

    /**
     * 修正申請の詳細・承認画面（show等）の閲覧権限があるかどうかを判定する
     *
     * @param  User  $user  ログイン中のユーザー
     * @param  AttendanceCorrection  $correction  判定対象の勤怠修正申請モデル
     * @return bool 閲覧を許可する場合はtrue、それ以外はfalse
     */
    public function view(User $user, AttendanceCorrection $correction): bool
    {
        // 管理者であれば無条件でOK（一般ユーザーはそもそもこの画面に来ない想定）
        return $user->is_admin;
    }

    /**
     * 修正申請の承認処理（approve）の権限があるかどうかを判定する
     *
     * @param  User  $user  ログイン中のユーザー
     * @param  AttendanceCorrection  $correction  判定対象の勤怠修正申請モデル
     * @return bool 承認を許可する場合はtrue、それ以外はfalse
     */
    public function approve(User $user, AttendanceCorrection $correction): bool
    {
        // 承認も管理者のみ
        return $user->is_admin;
    }
}
