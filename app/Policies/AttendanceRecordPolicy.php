<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;

class AttendanceRecordPolicy
{
    /**
     * 一般ユーザー用: 自分の勤怠一覧画面の閲覧権限
     */
    public function viewAny(User $user)
    {
        // ログインしていれば一般ユーザーとして閲覧を許可
        // （あるいは一般ユーザーだけに絞るなら !$user->is_admin など）
        return true;
    }

    /**
     * 管理者用: 全ユーザーの勤怠一覧画面の閲覧権限
     */
    public function viewAnyAdmin(User $user)
    {
        // 管理者のみ許可
        return $user->is_admin;
    }

    /**
     * 勤怠の閲覧（詳細画面など）の権限
     */
    public function view(User $user, AttendanceRecord $attendance)
    {
        // 管理者、または自分の勤怠であれば許可
        return $user->is_admin || $user->id === $attendance->user_id;
    }

    /**
     * 一般ユーザーによる「修正申請の作成」の権限
     */
    public function request(User $user, AttendanceRecord $attendance)
    {
        // 管理者、または自分の勤怠であれば申請を許可
        return $user->is_admin || $user->id === $attendance->user_id;
    }

    /**
     * 管理者による「勤怠データの直接修正」の権限
     */
    public function modify(User $user, AttendanceRecord $attendance)
    {
        // 直接修正は管理者だけが許可（※あるいは必要なら本人も）
        return $user->is_admin;
    }

    /**
     * すべての認可チェックの前に実行される処理
     * 管理者の場合は他の判定をスキップして全許可する
     */
    public function before(User $user, $ability)
    {
        // ユーザーが管理者権限を持っている場合の判定例
        // （※プロジェクトのロール定義に合わせて $user->role === 'admin' などに書き換えてください）
        if ($user->is_admin) { // または管理者判定のロジック
            return true;
        }
    }

    /**
     * 更新（update）の認可
     */
    public function update(User $user, AttendanceRecord $attendanceRecord): bool
    {
        // ログイン中のユーザーIDと、勤怠レコードの user_id が一致していれば本人のものとして許可
        return $user->id === $attendanceRecord->user_id;
    }

    /**
     * 削除（destroy）の認可
     */
    public function delete(User $user, AttendanceRecord $attendanceRecord): bool
    {
        // 更新と同様に本人のみ許可
        return $user->id === $attendanceRecord->user_id;
    }
}
