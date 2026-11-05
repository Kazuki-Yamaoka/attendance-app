<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AttendancePolicy
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
    public function update(User $user, AttendanceRecord $attendance)
    {
        // 直接修正は管理者だけが許可（※あるいは必要なら本人も）
        return $user->is_admin;
    }
}
