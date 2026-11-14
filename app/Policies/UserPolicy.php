<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * ユーザー一覧（またはモデル一覧）の閲覧権限があるかどうかを判定する
     *
     * @param  User  $user  ログイン中のユーザー
     * @return bool 閲覧を許可する場合はtrue、それ以外はfalse
     */
    public function viewAny(User $user): bool
    {
        // 管理者のみ許可
        return $user->is_admin;
    }
}
