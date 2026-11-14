<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;

class AttendanceRecordPolicy
{
    /**
     * 一般ユーザー用: 自分の勤怠一覧画面の閲覧権限があるかどうかを判定する
     *
     * @param  User  $user  ログイン中のユーザー
     * @return bool 閲覧を許可する場合はtrue、それ以外はfalse
     */
    public function viewAny(User $user): bool
    {
        // ログインしていれば一般ユーザーとして閲覧を許可
        return true;
    }

    /**
     * 管理者用: 全ユーザーの勤怠一覧画面の閲覧権限があるかどうかを判定する
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
     * 指定された勤怠の詳細画面などの閲覧権限があるかどうかを判定する
     *
     * @param  User  $user  ログイン中のユーザー
     * @param  AttendanceRecord  $attendance  判定対象の勤怠レコード
     * @return bool 閲覧を許可する場合はtrue、それ以外はfalse
     */
    public function view(User $user, AttendanceRecord $attendance): bool
    {
        // 管理者、または自分の勤怠であれば許可
        return $user->is_admin || $user->id === $attendance->user_id;
    }

    /**
     * 一般ユーザーによる「修正申請の作成」の権限があるかどうかを判定する
     *
     * @param  User  $user  ログイン中のユーザー
     * @param  AttendanceRecord  $attendance  判定対象の勤怠レコード
     * @return bool 申請を許可する場合はtrue、それ以外はfalse
     */
    public function request(User $user, AttendanceRecord $attendance): bool
    {
        // 管理者、または自分の勤怠であれば申請を許可
        return $user->is_admin || $user->id === $attendance->user_id;
    }

    /**
     * 管理者による「勤怠データの直接修正」の権限があるかどうかを判定する
     *
     * @param  User  $user  ログイン中のユーザー
     * @param  AttendanceRecord  $attendance  判定対象の勤怠レコード
     * @return bool 直接修正を許可する場合はtrue、それ以外はfalse
     */
    public function modify(User $user, AttendanceRecord $attendance): bool
    {
        // 直接修正は管理者だけが許可
        return $user->is_admin;
    }

    /**
     * すべての認可チェックの前に実行される処理
     * 管理者の場合は他の判定をスキップして全許可する
     *
     * @param  User  $user  ログイン中のユーザー
     * @param  string  $ability  実行しようとしているアクション名
     * @return bool|null 管理者の場合はtrue、それ以外はnull（通常の判定へ進む）
     */
    public function before(User $user, string $ability): ?bool
    {
        // ユーザーが管理者権限を持っている場合は全許可
        if ($user->is_admin) {
            return true;
        }

        return null;
    }

    /**
     * 勤怠データの更新（update）の認可があるかどうかを判定する
     *
     * @param  User  $user  ログイン中のユーザー
     * @param  AttendanceRecord  $attendanceRecord  判定対象の勤怠レコード
     * @return bool 更新を許可する場合はtrue、それ以外はfalse
     */
    public function update(User $user, AttendanceRecord $attendanceRecord): bool
    {
        // ログイン中のユーザーIDと、勤怠レコードの user_id が一致していれば本人のものとして許可
        return $user->id === $attendanceRecord->user_id;
    }

    /**
     * 勤怠データの削除（destroy）の認可があるかどうかを判定する
     *
     * @param  User  $user  ログイン中のユーザー
     * @param  AttendanceRecord  $attendanceRecord  判定対象の勤怠レコード
     * @return bool 削除を許可する場合はtrue、それ以外はfalse
     */
    public function delete(User $user, AttendanceRecord $attendanceRecord): bool
    {
        // 更新と同様に本人のみ許可
        return $user->id === $attendanceRecord->user_id;
    }
}
