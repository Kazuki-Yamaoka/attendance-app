<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest; // ★自作のリクエストをインポート
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminLoginController extends Controller
{
    /**
     * 管理者用ログイン画面を表示する（ログイン中の場合はダッシュボードへリダイレクト）
     *
     * @return View|RedirectResponse ログイン画面のビュー、またはダッシュボードへのリダイレクト
     */
    public function create(): View|RedirectResponse
    {
        // すでに管理者としてログイン中なら、そのまま管理者ダッシュボードへ飛ばす
        if (Auth::guard('admin')->check()) {
            return redirect('/admin/attendance/list');
        }

        // 未ログインなら管理者ログイン画面を表示
        return view('admin.admin-login');
    }

    /**
     * 管理者用のログイン認証処理を実行する
     *
     * @param  AdminLoginRequest  $request  バリデーション済み管理者ログインリクエスト
     * @return RedirectResponse 認証成功後のリダイレクトレスポンス
     *
     * @throws ValidationException 認証失敗または管理者権限がない場合
     */
    public function store(AdminLoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');

        // 1. まずメールとパスワードで認証を試みる
        if (! Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => ['ログイン情報が登録されていません'],
            ]);
        }

        // 2. ★認証通ったあとに「本当に管理者権限（例: is_admin == 1）があるか？」をチェック！
        $user = Auth::guard('admin')->user();
        if (! $user->is_admin) { // 実際のカラム名に合わせて変更してください
            // 管理者じゃなければ強制ログアウトさせる
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => ['管理者権限がありません'],
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended('/admin/attendance/list');
    }

    /**
     * 管理者用ログアウト処理を実行する
     *
     * @param  Request  $request  HTTPリクエストインスタンス
     * @return RedirectResponse ログアウト後の管理者ログイン画面へのリダイレクト
     */
    public function logout(Request $request): RedirectResponse
    {
        // 管理者ガード（'admin'）を指定してログアウト
        Auth::guard('admin')->logout();

        // セッションを破棄してトークンを再生成
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 管理者ログイン画面へリダイレクト
        return redirect('/admin/login');
    }
}
