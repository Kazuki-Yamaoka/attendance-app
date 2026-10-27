<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest; // ★自作のリクエストをインポート
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;

class AdminLoginController extends Controller
{
    public function create()
    {
        // ★もしすでにログインしている場合
        if (auth()->check()) {
            // もし一般ユーザー（is_admin が false）としてログイン中なら、一般のダッシュボードへ強制送還
            if (!auth()->user()->is_admin) {
                return redirect('/attendance')->with('error', 'すでに一般ユーザーとしてログインしています。');
            }
            
            // すでに管理者としてログイン中なら、管理者ダッシュボードへ飛ばす
            return redirect('/admin/attendance/list');
        }

        return view('admin.admin-login');
    }

    // 例：管理者用ログインコントローラーの store メソッドなど
    public function store(AdminLoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        // 1. まずメールとパスワードで認証を試みる
        if (!Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) 
        {
            //  dd('ここを通った：メールかパスワードが違います'); // ★確認1
            
            throw ValidationException::withMessages([
                'email' => ['ログイン情報が登録されていません。'],
            ]);
        }

        // 2. ★認証通ったあとに「本当に管理者権限（例: is_admin == 1）があるか？」をチェック！
        $user = Auth::guard('admin')->user();
        if (!$user->is_admin) // 実際のカラム名に合わせて変更してください
            {
                // dd('ここを通った：is_admin が true ではありません。現在の値は：', $user->is_admin); // ★確認2
 
                // 管理者じゃなければ強制ログアウトさせる
                Auth::guard('admin')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw ValidationException::withMessages([
                    'email' => ['管理者権限がありません。'],
                ]);
            }

        $request->session()->regenerate(); 

        // dd('ここを通った：ログイン成功！本来ならリダイレクトします'); // ★確認3

        return redirect()->intended('/admin/attendance/list');
    }
}
