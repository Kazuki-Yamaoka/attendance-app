<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserLoginController extends Controller
{
    /**
     * 一般ユーザー用ログイン画面を表示する
     *
     * @return View ログイン画面のビューインスタンス
     */
    public function create(): View
    {
        return view('user.user-login'); // 一般用ログイン画面
    }

    /**
     * 一般ユーザーのログイン認証処理を実行する
     *
     * @param  UserLoginRequest  $request  バリデーション済みログインリクエスト
     * @return RedirectResponse 認証成功後のリダイレクトレスポンス
     *
     * @throws ValidationException 認証に失敗した場合
     */
    public function store(UserLoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => ['ログイン情報が登録されていません'],
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended('/attendance'); // ログイン成功時
    }
}
