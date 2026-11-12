<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered; // 追加
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * 会員登録画面を表示する
     */
    public function create(): View
    {
        return view('user.register');
    }

    /**
     * 会員登録処理を実行する
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        // 1. バリデーション済みデータを取得
        $validated = $request->validated();

        // 2. ユーザーを作成（パスワードをハッシュ化）
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // 3. 登録イベントを発火し、確認メールを自動送信する
        event(new Registered($user));

        // 4. ユーザーをログイン状態にする（認証待ちの状態）
        Auth::login($user);

        // 5. メール認証を促す画面（verification.notice）にリダイレクトする
        return redirect()->route('verification.notice');
    }
}
